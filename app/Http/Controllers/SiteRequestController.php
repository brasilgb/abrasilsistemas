<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Solicitações": contatos recebidos pelo formulário do site (`leads.source = site`).
 *
 * Não é CRM: sem funil, score, tarefas ou importação (isso é do VetorLeads). A equipe só
 * acompanha a situação de cada pedido. Leads de outras origens (prospects importados no
 * tempo do CRM) continuam no banco, mas não aparecem nem abrem aqui (404).
 */
class SiteRequestController extends Controller
{
    /** Filtros de situação aceitos em ?situacao= (além das próprias situações). */
    private const GROUP_FILTERS = ['abertas', 'todas'];

    public function index(Request $request): Response
    {
        $status = (string) $request->query('situacao', 'abertas');
        $status = in_array($status, [...self::GROUP_FILTERS, ...array_keys(Lead::REQUEST_STATUSES)], true) ? $status : 'abertas';
        $search = trim((string) $request->query('busca', ''));

        $requests = Lead::query()
            ->siteRequests()
            ->withCount('whatsappConversations')
            ->when($status === 'abertas', fn (Builder $query) => $query->whereIn('request_status', Lead::OPEN_REQUEST_STATUSES))
            ->when(isset(Lead::REQUEST_STATUSES[$status]), fn (Builder $query) => $query->where('request_status', $status))
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'received_at' => $lead->created_at?->toIso8601String(),
                'contact_name' => $lead->contact_name,
                'company_name' => $lead->company_name,
                'whatsapp' => $lead->whatsapp,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'project_type' => $this->projectTypeLabel($lead),
                'excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $lead->notes) ?? '', 140),
                'source_page' => $lead->source_page,
                'request_status' => $lead->request_status,
                'has_conversation' => $lead->whatsapp_conversations_count > 0,
            ]);

        return Inertia::render('solicitacoes/index', [
            'requests' => $requests,
            'filters' => ['situacao' => $status, 'busca' => $search],
            'statuses' => Lead::REQUEST_STATUSES,
            'counts' => $this->counts(),
        ]);
    }

    public function show(Lead $solicitacao): Response
    {
        abort_unless($solicitacao->isSiteRequest(), 404);

        $conversations = $solicitacao->whatsappConversations()
            ->orderByDesc('last_message_at')
            ->get(['id', 'session', 'phone', 'last_message_at', 'last_message_preview']);

        return Inertia::render('solicitacoes/show', [
            'request' => [
                'id' => $solicitacao->id,
                'received_at' => $solicitacao->created_at?->toIso8601String(),
                'last_contact_at' => $solicitacao->last_contacted_at?->toIso8601String(),
                'contact_name' => $solicitacao->contact_name,
                'company_name' => $solicitacao->company_name,
                'whatsapp' => $solicitacao->whatsapp,
                'phone' => $solicitacao->phone,
                'email' => $solicitacao->email,
                'project_type' => $this->projectTypeLabel($solicitacao),
                'message' => $solicitacao->notes,
                'source_page' => $solicitacao->source_page,
                'request_status' => $solicitacao->request_status,
            ],
            'conversations' => $conversations->map(fn (WhatsappConversation $conversation) => [
                'id' => $conversation->id,
                'phone' => $conversation->phone,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'preview' => $conversation->last_message_preview,
            ]),
            // Registros anteriores (contatos repetidos pelo formulário e o histórico do tempo do CRM), só leitura.
            'history' => $solicitacao->activities()
                ->latest()
                ->limit(50)
                ->get(['id', 'type', 'description', 'created_at'])
                ->map(fn (LeadActivity $activity) => [
                    'id' => $activity->id,
                    'type' => LeadActivity::TYPES[$activity->type] ?? $activity->type,
                    'description' => $activity->description,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ]),
            'statuses' => Lead::REQUEST_STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Lead $solicitacao): RedirectResponse
    {
        abort_unless($solicitacao->isSiteRequest(), 404);

        $data = $request->validate([
            'request_status' => ['required', 'string', Rule::in(array_keys(Lead::REQUEST_STATUSES))],
        ], [
            'request_status.required' => 'Escolha a situação da solicitação.',
            'request_status.in' => 'Situação inválida.',
        ]);

        $solicitacao->forceFill(['request_status' => $data['request_status']])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Situação alterada para "'.Lead::REQUEST_STATUSES[$data['request_status']].'".',
        ]);

        return back();
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(function (Builder $query) use ($like, $digits) {
            $query->where('contact_name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('email', 'like', $like);

            if (strlen($digits) >= 4) {
                $query->orWhere('whatsapp', 'like', "%{$digits}%")
                    ->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $byStatus = Lead::query()
            ->siteRequests()
            ->selectRaw('request_status, count(*) as total')
            ->groupBy('request_status')
            ->pluck('total', 'request_status');

        $counts = collect(Lead::REQUEST_STATUSES)->map(fn ($label, $status) => (int) ($byStatus[$status] ?? 0))->all();

        return [
            'abertas' => array_sum(array_intersect_key($counts, array_flip(Lead::OPEN_REQUEST_STATUSES))),
            'todas' => (int) $byStatus->sum(),
            ...$counts,
        ];
    }

    private function projectTypeLabel(Lead $lead): string
    {
        return Lead::CONTACT_PROJECT_TYPES[$lead->product] ?? Lead::PRODUCTS[$lead->product] ?? $lead->product;
    }
}
