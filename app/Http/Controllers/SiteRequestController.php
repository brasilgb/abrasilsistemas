<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\WhatsappConversation;
use App\Services\VisitOrigin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Solicitações": contatos recebidos pelo formulário do site (`leads.source = site`).
 *
 * A equipe acompanha cada pedido do primeiro contato ao ganho ou perda, com observações e histórico.
 * Prospecção, score, tarefas, importação e automações continuam no VetorLeads. Leads de outras origens (prospects importados no
 * tempo do CRM) continuam no banco, mas não aparecem nem abrem aqui (404).
 */
class SiteRequestController extends Controller
{
    /** Filtros de situação aceitos em ?situacao= (além das próprias situações). */
    private const GROUP_FILTERS = ['abertas', 'todas'];

    /** Fuso usado nos filtros de data (o banco grava em UTC). */
    private const TIMEZONE = 'America/Sao_Paulo';

    public function index(Request $request): Response
    {
        $status = (string) $request->query('situacao', 'abertas');
        $status = in_array($status, [...self::GROUP_FILTERS, ...array_keys(Lead::REQUEST_STATUSES)], true) ? $status : 'abertas';
        $filters = $this->filters($request);

        $requests = $this->filtered($filters)
            ->withCount('whatsappConversations')
            ->when($status === 'abertas', fn (Builder $query) => $query->whereIn('request_status', Lead::OPEN_REQUEST_STATUSES))
            ->when(isset(Lead::REQUEST_STATUSES[$status]), fn (Builder $query) => $query->where('request_status', $status))
            // Quem escreveu por último aparece primeiro, mesmo que a solicitação seja antiga.
            ->orderByRaw('COALESCE(last_contacted_at, created_at) DESC')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'received_at' => $lead->created_at?->toIso8601String(),
                'last_contact_at' => $lead->last_contacted_at?->toIso8601String(),
                'contact_name' => $lead->contact_name,
                'company_name' => $lead->company_name,
                'whatsapp' => $lead->whatsapp,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'project_type' => $this->projectTypeLabel($lead),
                'excerpt' => Str::limit(preg_replace('/\s+/', ' ', (string) $lead->notes) ?? '', 140),
                'source_page' => $lead->source_page,
                'origin' => $lead->commercialOrigin(),
                'request_status' => $lead->request_status,
                'has_conversation' => $lead->whatsapp_conversations_count > 0,
            ]);

        return Inertia::render('solicitacoes/index', [
            'requests' => $requests,
            'filters' => ['situacao' => $status, ...$filters],
            'statuses' => Lead::REQUEST_STATUSES,
            'services' => Lead::CONTACT_PROJECT_TYPES,
            'origins' => $this->originOptions(),
            'counts' => $this->counts($filters),
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
                'entry_page' => $solicitacao->entry_page,
                'origin' => $solicitacao->commercialOrigin(),
                'utm' => array_filter($solicitacao->only(VisitOrigin::UTM_FIELDS)),
                'marketing_consent_at' => $solicitacao->marketing_consent_at?->toIso8601String(),
                'request_status' => $solicitacao->request_status,
                'lost_reason' => $solicitacao->lost_reason,
            ],
            'conversations' => $conversations->map(fn (WhatsappConversation $conversation) => [
                'id' => $conversation->id,
                'phone' => $conversation->phone,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'preview' => $conversation->last_message_preview,
            ]),
            // Mesmo e-mail ou telefone: podem ser da mesma pessoa, mas os dados nunca são misturados.
            'related' => $solicitacao->relatedSiteRequests()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Lead $lead) => [
                    'id' => $lead->id,
                    'contact_name' => $lead->contact_name,
                    'project_type' => $this->projectTypeLabel($lead),
                    'received_at' => $lead->created_at?->toIso8601String(),
                    'request_status' => $lead->request_status,
                ]),
            // Novos contatos, mudanças de situação, observações da equipe e o histórico do tempo do CRM.
            'history' => $solicitacao->activities()
                ->with('user:id,name')
                ->latest()
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (LeadActivity $activity) => [
                    'id' => $activity->id,
                    'type' => LeadActivity::TYPES[$activity->type] ?? $activity->type,
                    'description' => $activity->description,
                    'author' => $activity->user?->name,
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
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'request_status.required' => 'Escolha a situação da solicitação.',
            'request_status.in' => 'Situação inválida.',
            'note.max' => 'O comentário pode ter até 255 caracteres.',
        ]);

        $changed = DB::transaction(function () use ($solicitacao, $data, $request) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($solicitacao->id);

            if ($lead->request_status === $data['request_status']) {
                return false;
            }

            $previous = Lead::REQUEST_STATUSES[$lead->request_status] ?? 'Sem situação';
            $note = trim((string) ($data['note'] ?? ''));

            $lead->forceFill([
                'request_status' => $data['request_status'],
                'lost_reason' => $data['request_status'] === 'lost' ? ($note ?: null) : $lead->lost_reason,
            ])->save();

            $lead->activities()->create([
                'type' => 'status',
                'status' => $data['request_status'],
                'user_id' => $request->user()->id,
                'description' => $previous.' → '.Lead::REQUEST_STATUSES[$data['request_status']].($note !== '' ? PHP_EOL.$note : ''),
            ]);

            return true;
        });

        if ($changed) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Situação alterada para "'.Lead::REQUEST_STATUSES[$data['request_status']].'".',
            ]);
        }

        return back();
    }

    public function storeNote(Request $request, Lead $solicitacao): RedirectResponse
    {
        abort_unless($solicitacao->isSiteRequest(), 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
        ], [
            'description.required' => 'Escreva a observação.',
            'description.max' => 'A observação pode ter até 5.000 caracteres.',
        ]);

        $solicitacao->activities()->create([
            'type' => 'note',
            'user_id' => $request->user()->id,
            'description' => $data['description'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Observação registrada.']);

        return back();
    }

    /**
     * Filtros da lista, menos a situação. Valores desconhecidos são ignorados.
     *
     * @return array{busca: string, servico: string, origem: string, de: string, ate: string}
     */
    private function filters(Request $request): array
    {
        $text = fn (string $key) => is_string($value = $request->query($key)) ? trim($value) : '';
        $date = fn (string $key) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text($key), $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $text($key) : '';

        return [
            'busca' => $text('busca'),
            'servico' => isset(Lead::CONTACT_PROJECT_TYPES[$text('servico')]) ? $text('servico') : '',
            'origem' => isset(VisitOrigin::GROUPS[$text('origem')]) || str_starts_with($text('origem'), 'utm:') ? $text('origem') : '',
            'de' => $date('de'),
            'ate' => $date('ate'),
        ];
    }

    /**
     * @param  array{busca: string, servico: string, origem: string, de: string, ate: string}  $filters
     * @return Builder<Lead>
     */
    private function filtered(array $filters): Builder
    {
        return Lead::query()
            ->siteRequests()
            ->when($filters['busca'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['busca']))
            ->when($filters['servico'] !== '', fn (Builder $query) => $query->where('product', $filters['servico']))
            ->when($filters['origem'] !== '', fn (Builder $query) => VisitOrigin::applyFilter($query, $filters['origem']))
            ->when($filters['de'] !== '', fn (Builder $query) => $query->where('created_at', '>=', Date::parse($filters['de'], self::TIMEZONE)->startOfDay()->utc()))
            ->when($filters['ate'] !== '', fn (Builder $query) => $query->where('created_at', '<=', Date::parse($filters['ate'], self::TIMEZONE)->endOfDay()->utc()));
    }

    /**
     * Grupos de origem e as campanhas (utm_source) que já trouxeram solicitações.
     *
     * @return array<string, string>
     */
    private function originOptions(): array
    {
        $sources = Lead::query()->siteRequests()->whereNotNull('utm_source')->distinct()->orderBy('utm_source')->limit(50)->pluck('utm_source');

        return [
            ...VisitOrigin::GROUPS,
            ...$sources->mapWithKeys(fn (string $source) => ['utm:'.$source => 'Campanha: '.$source])->all(),
        ];
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
     * @param  array{busca: string, servico: string, origem: string, de: string, ate: string}  $filters
     * @return array<string, int>
     */
    private function counts(array $filters): array
    {
        $byStatus = $this->filtered($filters)
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
