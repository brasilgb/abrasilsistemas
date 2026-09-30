<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\LeadImportRequest;
use App\Http\Requests\Leads\LeadRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ProspectProduct;
use App\Models\User;
use App\Services\LeadWhatsappService;
use App\Services\ProductRegistrationChecker;
use App\Services\WhatsappBusinessHours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /** Telas que alteram só o status do lead (PATCH leads/{lead}/status), com o texto do histórico. */
    private const STATUS_ORIGINS = [
        'kanban' => 'pelo Kanban',
        'whatsapp' => 'pela conversa WhatsApp',
    ];

    /** Tamanho da página da fila "Aguardando resposta". */
    public const AWAITING_REPLY_PER_PAGE = 25;

    /** Trecho da última mensagem recebida exibido na fila. */
    public const AWAITING_REPLY_EXCERPT_LENGTH = 160;

    /** Dias de períodos de atendimento enviados à fila (o polling renova a cada 15 s). */
    public const AWAITING_REPLY_CLOCK_DAYS = 14;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'product', 'status', 'city', 'state', 'industry', 'follow_up', 'owner']);

        $baseQuery = $this->applyCommonFilters(Lead::query()->with(['user:id,name', 'prospectProduct:id,name,active']), $filters, $request);

        $this->applyFollowUpFilter($baseQuery, $filters['follow_up'] ?? null);

        $taskQuery = (clone $baseQuery)->whereNotIn('status', Lead::CLOSED_STATUSES);

        // withCount('unreadWhatsappMessages') só nas consultas exibidas: no baseQuery, a subconsulta
        // entraria também nas agregações das métricas (count/groupBy).
        $leads = (clone $baseQuery)
            ->withCount('unreadWhatsappMessages')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('leads/index', [
            'filters' => $filters,
            'kanbanTotal' => (clone $baseQuery)->count(),
            'kanbanLeads' => (clone $baseQuery)
                ->withCount('unreadWhatsappMessages')
                ->latest()
                ->limit(500)
                ->get()
                ->sortByDesc('lead_score')
                ->values(),
            'leads' => $leads,
            'metrics' => $this->metrics(clone $baseQuery),
            // Contador global (sem os filtros da tela), o mesmo do Dashboard.
            'awaitingReplyTotal' => Lead::query()->awaitingWhatsappReply()->count(),
            'awaitingReplyCritical' => Lead::query()->whatsappReplySla('critical')->count(),
            'taskLeads' => [
                'overdue' => (clone $taskQuery)->withCount('unreadWhatsappMessages')->followUpState('overdue')->orderBy('next_follow_up_at')->limit(50)->get(),
                'today' => (clone $taskQuery)->withCount('unreadWhatsappMessages')->followUpState('today')->orderBy('next_follow_up_at')->limit(50)->get(),
                'without_follow_up' => (clone $taskQuery)->withCount('unreadWhatsappMessages')->followUpState('none')->latest()->limit(50)->get(),
            ],
            'taskTotals' => [
                'overdue' => (clone $taskQuery)->followUpState('overdue')->count(),
                'today' => (clone $taskQuery)->followUpState('today')->count(),
                'without_follow_up' => (clone $taskQuery)->followUpState('none')->count(),
            ],
            'lostReasons' => Lead::LOST_REASONS,
            'products' => Lead::PRODUCTS,
            'productOptions' => $this->productFilterOptions(),
            // Texto do produto vinculado para o botão WhatsApp (CRM-WA-22), uma vez por produto.
            'productWhatsappTexts' => ProspectProduct::manualWhatsappTexts(),
            'statuses' => Lead::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Opções do filtro Produto: os produtos do catálogo (inclusive inativos, para consultar o
     * histórico) e, para os leads ainda sem vínculo, cada texto legado de `leads.product`.
     *
     * @return list<array{value: string, label: string}>
     */
    private function productFilterOptions(): array
    {
        $catalog = ProspectProduct::query()
            ->orderBy('name')
            ->get(['id', 'name', 'active'])
            ->map(fn (ProspectProduct $product) => [
                'value' => (string) $product->id,
                'label' => $product->active ? $product->name : "{$product->name} (inativo)",
            ]);

        $legacy = Lead::query()
            ->whereNull('prospect_product_id')
            ->distinct()
            ->orderBy('product')
            ->pluck('product')
            ->map(fn (string $product) => [
                'value' => $product,
                'label' => (Lead::PRODUCTS[$product] ?? $product).' (sem vínculo)',
            ]);

        return array_values($catalog->concat($legacy)->all());
    }

    /**
     * Fila "Aguardando resposta": leads cuja última mensagem WhatsApp veio do cliente, do que
     * espera há mais tempo para o mais recente. Responder é na conversa do lead (tela de edição).
     */
    public function awaitingReply(Request $request, WhatsappBusinessHours $businessHours): Response
    {
        $filters = $request->only(['search', 'product', 'status', 'owner', 'sla']);
        // Segundo cheio, como no cálculo da espera: o relógio da página e next_sla_at batem no limite.
        $now = now()->startOfSecond();

        // Filtro sla no banco, antes da paginação (não pagina a fila inteira para filtrar depois).
        $leads = $this->applyCommonFilters(Lead::query()->with(['user:id,name', 'prospectProduct:id,name,active']), $filters, $request)
            ->whatsappReplyQueue()
            ->when($filters['sla'] ?? null, fn ($query, string $sla) => $query->whatsappReplySla($sla))
            ->withCount('unreadWhatsappMessages')
            ->paginate(self::AWAITING_REPLY_PER_PAGE)
            ->withQueryString();

        $leads->getCollection()->each(function (Lead $lead) use ($businessHours, $now): void {
            $lead->setAttribute('last_inbound_message', Str::limit(
                Str::squish((string) $lead->getAttribute('last_inbound_message')),
                self::AWAITING_REPLY_EXCERPT_LENGTH,
            ));
            // Espera em tempo de atendimento até slaClock.now; a página soma o que passar dali em diante.
            $seconds = $businessHours->elapsedSeconds($lead->getAttribute('awaiting_reply_since'), $now);
            // Próxima mudança de nível: instante real em que a espera útil atinge o próximo limite
            // (a página só formata). Null no Crítico e sem dias de atendimento.
            $nextLevel = Lead::nextWhatsappReplySla($seconds);
            $nextAt = $nextLevel === null ? null : $businessHours->addBusinessSeconds(
                $now,
                Lead::WHATSAPP_REPLY_SLA[$nextLevel]['from_minutes'] * 60 - $seconds,
            );

            $lead->setAttribute('awaiting_reply_business_seconds', $seconds);
            $lead->setAttribute('next_sla_level', $nextAt === null ? null : $nextLevel);
            $lead->setAttribute('next_sla_at', $nextAt?->toJSON());
        });

        return Inertia::render('leads/awaiting-reply', [
            'filters' => $filters,
            'leads' => $leads,
            'slaClock' => [
                'now' => $now->toJSON(),
                // Próximos períodos de atendimento: o relógio da página só anda dentro deles.
                'periods' => array_map(
                    fn (array $period) => [$period[0]->toJSON(), $period[1]->toJSON()],
                    $businessHours->periodsBetween($now, $now->addDays(self::AWAITING_REPLY_CLOCK_DAYS)),
                ),
                'schedule' => $businessHours->description(),
            ],
            'slaLevels' => Lead::WHATSAPP_REPLY_SLA,
            'productOptions' => $this->productFilterOptions(),
            'statuses' => Lead::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Lead>
     */
    private function applyCommonFilters(Builder $query, array $filters, Request $request): Builder
    {
        return $query
            ->when(($filters['owner'] ?? null) === 'mine', fn ($query) => $query->where('user_id', $request->user()->id))
            ->when(is_numeric($filters['owner'] ?? null), fn ($query) => $query->where('user_id', (int) $filters['owner']))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%");
                });
            })
            // Número: produto do catálogo. Texto: lead ainda sem vínculo com esse valor legado.
            ->when($filters['product'] ?? null, fn ($query, string $product) => ctype_digit($product)
                ? $query->where('prospect_product_id', (int) $product)
                : $query->whereNull('prospect_product_id')->where('product', $product))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', strtoupper($state)))
            ->when($filters['industry'] ?? null, fn ($query, string $industry) => $query->where('industry', 'like', "%{$industry}%"));
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function applyFollowUpFilter(Builder $query, ?string $followUp): Builder
    {
        // Só prospects abertos: Convertido/Perdido nunca aparecem como follow-up pendente.
        return $query->when($followUp, fn ($query, string $followUp) => $query->followUpState($followUp));
    }

    /**
     * @param  Builder<Lead>  $query
     * @return array<string, mixed>
     */
    private function metrics(Builder $query): array
    {
        $total = (clone $query)->count();
        $converted = (clone $query)->where('status', 'converted')->count();
        $open = (clone $query)->whereNotIn('status', Lead::CLOSED_STATUSES)->count();
        $overdue = (clone $query)->followUpState('overdue')->count();
        $today = (clone $query)->followUpState('today')->count();
        $upcoming = (clone $query)->followUpState('upcoming')->count();
        $withoutFollowUp = (clone $query)->followUpState('none')->count();
        $highPriority = (clone $query)
            ->whereNotIn('status', ['converted', 'lost'])
            ->get(['id', 'status', 'next_follow_up_at', 'whatsapp', 'email', 'contact_name', 'website', 'instagram', 'notes'])
            ->where('priority', 'high')
            ->count();

        return [
            'total' => $total,
            'open' => $open,
            'converted' => $converted,
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            'high_priority' => $highPriority,
            'follow_ups' => [
                'overdue' => $overdue,
                'today' => $today,
                'upcoming' => $upcoming,
                'without_follow_up' => $withoutFollowUp,
            ],
            'by_status' => (clone $query)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('leads/create', [
            'lostReasons' => Lead::LOST_REASONS,
            'products' => Lead::PRODUCTS,
            'prospectProducts' => ProspectProduct::options(),
            'statuses' => Lead::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $data = $request->leadData();
        $data['lost_reason'] = $data['status'] === 'lost' ? ($data['lost_reason'] ?? null) : null;
        $this->ensureLeadIsUnique($data);

        $lead = Lead::query()->create([
            ...$data,
            'user_id' => $data['user_id'] ?? $request->user()->id,
        ]);

        if ($lead->status !== 'new') {
            $lead->forceFill(['last_contacted_at' => now()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead criado.']);

        return to_route('leads.index');
    }

    public function import(LeadImportRequest $request): RedirectResponse
    {
        $file = $request->file('csv');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Nao foi possivel ler o CSV.']);

            return back();
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'O CSV esta vazio.']);

            return back();
        }

        $delimiter = $this->detectCsvDelimiter($firstLine);
        $headers = $this->normalizeHeaders(str_getcsv($firstLine, $delimiter));
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $data = $this->leadDataFromCsvRow($headers, $row);

            if (($data['company_name'] ?? null) === null) {
                $skipped++;

                continue;
            }

            $validator = Validator::make($data, $this->importRules());

            if ($validator->fails()) {
                $skipped++;

                continue;
            }

            if ($this->duplicateLeadExists($validator->validated())) {
                $skipped++;

                continue;
            }

            Lead::query()->create([
                ...$validator->validated(),
                'user_id' => $request->user()->id,
            ]);

            $created++;
        }

        fclose($handle);

        $message = "{$created} lead(s) importado(s).";

        if ($skipped > 0) {
            $message .= " {$skipped} linha(s) ignorada(s).";
        }

        Inertia::flash('toast', ['type' => $created > 0 ? 'success' : 'error', 'message' => $message]);

        return to_route('leads.index');
    }

    public function edit(Lead $lead): Response
    {
        $lead->load([
            'activities' => fn ($query) => $query->with('user:id,name')->latest(),
            'user:id,name',
            'prospectProduct:id,name,active,whatsapp_text,trial_days,registration_check_mode,registration_api_url,registration_lookup_field,registration_api_token',
        ]);

        // O ID da mensagem no provedor (WAHA) é de uso interno; o histórico só precisa do message_status.
        $lead->activities->each->makeHidden('provider_message_id');

        // Abrir o lead exibe o histórico completo: as mensagens recebidas pendentes passam a lidas.
        // O histórico já carregado mantém read_at nulo nelas, para a página destacar o que é novo.
        $lead->unreadWhatsappMessages()->update(['read_at' => now()]);

        return Inertia::render('leads/edit', [
            'activityTypes' => LeadActivity::TYPES,
            'lead' => $lead,
            'lostReasons' => Lead::LOST_REASONS,
            'products' => Lead::PRODUCTS,
            // Texto WhatsApp do produto vinculado ao lead (CRM-WA-22); null usa o template legado.
            'productWhatsappText' => $lead->prospectProduct?->manualWhatsappText(),
            'prospectProducts' => ProspectProduct::options($lead->prospect_product_id),
            'statuses' => Lead::STATUSES,
            // Período de teste (CRM-WA-23): situação calculada e se "Iniciar" está disponível.
            'trialPeriod' => $lead->trialPeriod(),
            // Verificação de cadastro pela API do produto (CRM-WA-29); null no modo manual.
            'registrationCheck' => $lead->registrationCheck(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            // Destino já normalizado do envio manual (null quando o Lead não tem WhatsApp válido).
            'whatsappDestination' => LeadWhatsappService::normalizeNumber($lead->whatsapp),
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $previousStatus = $lead->status;
        $data = $request->leadData();
        $data['lost_reason'] = $data['status'] === 'lost' ? ($data['lost_reason'] ?? null) : null;
        $this->ensureLeadIsUnique($data, $lead);

        $lead->fill($data);

        if ($previousStatus === 'new' && $lead->status !== 'new' && $lead->last_contacted_at === null) {
            $lead->last_contacted_at = now();
        }

        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead atualizado.']);

        return to_route('leads.index');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prospect excluído.']);

        return to_route('leads.index');
    }

    public function clear(Request $request): RedirectResponse
    {
        $filters = $request->only(['search', 'product', 'status', 'city', 'state', 'industry', 'follow_up', 'owner']);
        $hasFilters = array_filter($filters) !== [];

        $query = $this->applyFollowUpFilter(
            $this->applyCommonFilters(Lead::query(), $filters, $request),
            $filters['follow_up'] ?? null
        );

        $deleted = $query->count();
        $query->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $hasFilters
                ? "{$deleted} prospect(s) removido(s) conforme os filtros aplicados."
                : "{$deleted} prospect(s) removido(s).",
        ]);

        return to_route('leads.index');
    }

    public function status(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            // De onde veio a mudança, só para o texto do histórico. Sem o campo, vale o Kanban.
            'origin' => ['nullable', 'string', Rule::in(array_keys(self::STATUS_ORIGINS))],
        ]);

        $previousStatus = $lead->status;
        $lead->status = $data['status'];
        $lead->lost_reason = $lead->status === 'lost' ? ($data['lost_reason'] ?? $lead->lost_reason) : null;

        if ($previousStatus === 'new' && $lead->status !== 'new' && $lead->last_contacted_at === null) {
            $lead->last_contacted_at = now();
        }

        $lead->save();

        $lead->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'note',
            'status' => $lead->status,
            'contacted_at' => now(),
            'description' => sprintf(
                'Status alterado de %s para %s %s%s.',
                Lead::STATUSES[$previousStatus] ?? $previousStatus,
                Lead::STATUSES[$lead->status] ?? $lead->status,
                self::STATUS_ORIGINS[$data['origin'] ?? 'kanban'],
                $lead->lost_reason ? ' (motivo: '.(Lead::LOST_REASONS[$lead->lost_reason] ?? $lead->lost_reason).')' : ''
            ),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status atualizado.']);

        return back();
    }

    /**
     * Agenda, altera ou remove o próximo contato (atalhos da conversa WhatsApp). O histórico
     * é registrado pelo próprio Lead ao salvar. Nunca dispara mensagem.
     */
    public function followUp(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'next_follow_up_at' => ['present', 'nullable', 'date'],
        ]);

        if ($data['next_follow_up_at'] !== null && $lead->isClosed()) {
            throw ValidationException::withMessages([
                'next_follow_up_at' => 'Prospect convertido ou perdido não tem próximo contato. Reabra o estágio antes de agendar.',
            ]);
        }

        $lead->next_follow_up_at = $data['next_follow_up_at'];
        $lead->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $lead->next_follow_up_at ? 'Próximo contato agendado.' : 'Próximo contato removido.',
        ]);

        return back();
    }

    /**
     * "Assumir atendimento" (fila Aguardando resposta e tela do lead). O responsável vem só da
     * sessão; um lead que já tem responsável nunca é tomado por aqui (transferir é pelo lote).
     */
    public function assignToMe(Request $request, Lead $lead): RedirectResponse
    {
        $user = $request->user();

        if ($lead->assignTo($user)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Atendimento assumido.']);

            return back();
        }

        // Outro operador assumiu antes (ou o lead já era deste usuário): nada é sobrescrito.
        $ownerId = $lead->fresh()?->user_id;

        Inertia::flash('toast', $ownerId === $user->id
            ? ['type' => 'success', 'message' => 'Este atendimento já é seu.']
            : ['type' => 'error', 'message' => 'Este lead já foi assumido por '.(User::query()->whereKey($ownerId)->value('name') ?? 'outro usuário').'.']);

        return back();
    }

    /**
     * "Iniciar período de teste" (CRM-WA-23). O prazo vem só do produto vinculado ao lead; um
     * teste já iniciado nunca é reiniciado ou recalculado por aqui, nem em cliques simultâneos.
     */
    public function startTrial(Request $request, Lead $lead): RedirectResponse
    {
        $blocker = $lead->trialStartBlocker();

        if ($blocker === null && $lead->startTrial($request->user())) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Período de teste iniciado.']);

            return back();
        }

        // Outra requisição iniciou antes (ou o vínculo mudou no meio): nada foi alterado.
        Inertia::flash('toast', ['type' => 'error', 'message' => $blocker ?? $lead->fresh()?->trialStartBlocker() ?? 'Não foi possível iniciar o período de teste.']);

        return back();
    }

    /**
     * "Verificar cadastro agora" (CRM-WA-29): a mesma consulta do agendamento, para um lead.
     * Cadastro confirmado inicia o teste pela regra de sempre; erro não muda nada no lead.
     */
    public function checkRegistration(Lead $lead, ProductRegistrationChecker $checker): RedirectResponse
    {
        $result = $checker->checkAndRecord($lead);

        [$type, $message] = match (true) {
            $result === null => ['error', 'Este lead não está aguardando verificação de cadastro.'],
            $result->isRegistered() && $result->trialStarted => ['success', 'Cadastro confirmado pela API. Período de teste iniciado.'],
            $result->isRegistered() => ['success', 'Cadastro confirmado pela API. O período de teste não foi iniciado: '.($lead->fresh()?->trialStartBlocker() ?? 'o lead mudou durante a consulta.')],
            $result->status === 'not_registered' => ['info', 'A API do produto informou que o prospect ainda não se cadastrou.'],
            default => ['error', 'Não foi possível verificar o cadastro: '.(Lead::REGISTRATION_CHECK_ERRORS[$result->error] ?? $result->error).'. Nada foi alterado.'],
        };

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $changesStatus = array_key_exists('status', $data) && $data['status'] !== null;
        $changesOwner = $request->has('user_id');

        if (! $changesStatus && ! $changesOwner) {
            return back();
        }

        $leads = Lead::query()->whereIn('id', $data['ids'])->get();

        foreach ($leads as $lead) {
            $updates = [];
            $previousStatus = $lead->status;

            if ($changesStatus) {
                $updates['status'] = $data['status'];
                $updates['lost_reason'] = $data['status'] === 'lost' ? ($data['lost_reason'] ?? $lead->lost_reason) : null;

                if ($previousStatus === 'new' && $data['status'] !== 'new' && $lead->last_contacted_at === null) {
                    $updates['last_contacted_at'] = now();
                }
            }

            if ($changesOwner) {
                $updates['user_id'] = $data['user_id'] ?? null;
            }

            $lead->forceFill($updates)->save();

            if ($changesStatus && $data['status'] !== $previousStatus) {
                $lead->activities()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'note',
                    'status' => $lead->status,
                    'contacted_at' => now(),
                    'description' => sprintf(
                        'Status alterado de %s para %s em atualização em lote%s.',
                        Lead::STATUSES[$previousStatus] ?? $previousStatus,
                        Lead::STATUSES[$lead->status] ?? $lead->status,
                        $lead->lost_reason ? ' (motivo: '.(Lead::LOST_REASONS[$lead->lost_reason] ?? $lead->lost_reason).')' : ''
                    ),
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $leads->count().' lead(s) atualizado(s).']);

        return back();
    }

    private function detectCsvDelimiter(string $line): string
    {
        return collect([',', ';', "\t"])
            ->sortByDesc(fn (string $delimiter) => count(str_getcsv($line, $delimiter)))
            ->first();
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn (?string $header) => $this->normalizeHeader((string) $header), $headers);
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->replace("\u{FEFF}", '')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    private function leadDataFromCsvRow(array $headers, array $row): array
    {
        $data = [
            'product' => 'vetoros',
            'source' => 'CSV',
            'status' => 'new',
        ];

        foreach ($headers as $index => $header) {
            $field = $this->leadFieldFromHeader($header);

            if ($field === null) {
                continue;
            }

            $value = trim((string) ($row[$index] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($field === 'notes' && isset($data['notes'])) {
                $data['notes'] .= PHP_EOL.$value;

                continue;
            }

            $data[$field] = $value;
        }

        if (isset($data['state'])) {
            $data['state'] = strtoupper($data['state']);
        }

        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }

        if (isset($data['whatsapp'])) {
            $data['whatsapp'] = preg_replace('/\D+/', '', $data['whatsapp']) ?? '';
        }

        if (isset($data['status'])) {
            $data['status'] = $this->normalizeStatus($data['status']);
        }

        if (isset($data['lost_reason'])) {
            $data['lost_reason'] = $this->normalizeLostReason($data['lost_reason']);
        }

        if (isset($data['product'])) {
            $data['product'] = $this->normalizeProduct($data['product']);
        }

        return array_map(fn (string $value) => Str::limit($value, 5000, ''), $data);
    }

    private function leadFieldFromHeader(string $header): ?string
    {
        $fields = [
            'company_name',
            'product',
            'contact_name',
            'industry',
            'city',
            'state',
            'phone',
            'whatsapp',
            'email',
            'website',
            'instagram',
            'source',
            'status',
            'lost_reason',
            'next_follow_up_at',
            'notes',
        ];

        return in_array($header, $fields, true) ? $header : null;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = $this->normalizeHeader($status);

        return [
            'novo' => 'new',
            'new' => 'new',
            'contatado' => 'contacted',
            'contacted' => 'contacted',
            'interessado' => 'interested',
            'interested' => 'interested',
            'reuniao' => 'meeting',
            'meeting' => 'meeting',
            'convertido' => 'converted',
            'converted' => 'converted',
            'perdido' => 'lost',
            'lost' => 'lost',
        ][$normalized] ?? 'new';
    }

    private function normalizeProduct(string $product): string
    {
        $normalized = $this->normalizeHeader($product);

        return [
            'vetoros' => 'vetoros',
            'vetor_os' => 'vetoros',
            'os' => 'vetoros',
            'assistencia' => 'vetoros',
            'assistencia_tecnica' => 'vetoros',
            'vetorpet' => 'vetorpet',
            'vetor_pet' => 'vetorpet',
            'pet' => 'vetorpet',
            'petshop' => 'vetorpet',
            'pet_shop' => 'vetorpet',
        ][$normalized] ?? 'vetoros';
    }

    private function normalizeLostReason(string $reason): string
    {
        $normalized = $this->normalizeHeader($reason);

        return [
            'preco' => 'price',
            'price' => 'price',
            'sem_interesse' => 'no_interest',
            'no_interest' => 'no_interest',
            'concorrente' => 'competitor',
            'competitor' => 'competitor',
            'fechou_com_concorrente' => 'competitor',
            'sem_resposta' => 'no_response',
            'no_response' => 'no_response',
            'fora_do_perfil' => 'bad_fit',
            'bad_fit' => 'bad_fit',
            'outro' => 'other',
            'other' => 'other',
        ][$normalized] ?? 'other';
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function importRules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', Rule::in(array_keys(Lead::PRODUCTS))],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureLeadIsUnique(array $data, ?Lead $except = null): void
    {
        if (! $this->duplicateLeadExists($data, $except)) {
            return;
        }

        throw ValidationException::withMessages([
            'company_name' => 'Já existe um lead com o mesmo e-mail, WhatsApp ou empresa e localização.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function duplicateLeadExists(array $data, ?Lead $except = null): bool
    {
        return Lead::isDuplicate($data, $except);
    }
}
