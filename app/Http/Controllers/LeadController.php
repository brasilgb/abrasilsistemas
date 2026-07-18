<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\LeadImportRequest;
use App\Http\Requests\Leads\LeadRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'product', 'status', 'city', 'state', 'industry', 'follow_up', 'owner']);

        $baseQuery = Lead::query()
            ->with('user:id,name')
            ->when(($filters['owner'] ?? null) === 'mine', fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%");
                });
            })
            ->when($filters['product'] ?? null, fn ($query, string $product) => $query->where('product', $product))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', strtoupper($state)))
            ->when($filters['industry'] ?? null, fn ($query, string $industry) => $query->where('industry', 'like', "%{$industry}%"))
            ->when($filters['follow_up'] ?? null, function ($query, string $followUp) {
                $query
                    ->when($followUp === 'overdue', fn ($query) => $query->whereDate('next_follow_up_at', '<', today()))
                    ->when($followUp === 'today', fn ($query) => $query->whereDate('next_follow_up_at', today()))
                    ->when($followUp === 'upcoming', fn ($query) => $query->whereDate('next_follow_up_at', '>', today()));
            });

        $leads = (clone $baseQuery)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('leads/index', [
            'filters' => $filters,
            'kanbanLeads' => (clone $baseQuery)
                ->latest()
                ->limit(200)
                ->get()
                ->sortByDesc('lead_score')
                ->values(),
            'leads' => $leads,
            'metrics' => $this->metrics(),
            'lostReasons' => Lead::LOST_REASONS,
            'products' => Lead::PRODUCTS,
            'statuses' => Lead::STATUSES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $total = Lead::query()->count();
        $converted = Lead::query()->where('status', 'converted')->count();
        $open = Lead::query()->whereNotIn('status', ['converted', 'lost'])->count();
        $overdue = Lead::query()->whereDate('next_follow_up_at', '<', today())->count();
        $today = Lead::query()->whereDate('next_follow_up_at', today())->count();
        $upcoming = Lead::query()->whereDate('next_follow_up_at', '>', today())->count();
        $highPriority = Lead::query()
            ->whereNotIn('status', ['converted', 'lost'])
            ->get()
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
            ],
            'by_status' => Lead::query()
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
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['lost_reason'] = $data['status'] === 'lost' ? ($data['lost_reason'] ?? null) : null;

        $lead = Lead::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
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
        return Inertia::render('leads/edit', [
            'activityTypes' => LeadActivity::TYPES,
            'lead' => $lead->load([
                'activities' => fn ($query) => $query->with('user:id,name')->latest(),
                'user:id,name',
            ]),
            'lostReasons' => Lead::LOST_REASONS,
            'products' => Lead::PRODUCTS,
            'statuses' => Lead::STATUSES,
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $previousStatus = $lead->status;
        $data = $request->validated();
        $data['lost_reason'] = $data['status'] === 'lost' ? ($data['lost_reason'] ?? null) : null;

        $lead->fill($data);

        if ($previousStatus === 'new' && $lead->status !== 'new' && $lead->last_contacted_at === null) {
            $lead->last_contacted_at = now();
        }

        $lead->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead atualizado.']);

        return to_route('leads.index');
    }

    public function status(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
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
                'Status alterado de %s para %s pelo Kanban%s.',
                Lead::STATUSES[$previousStatus] ?? $previousStatus,
                Lead::STATUSES[$lead->status] ?? $lead->status,
                $lead->lost_reason ? ' (motivo: '.(Lead::LOST_REASONS[$lead->lost_reason] ?? $lead->lost_reason).')' : ''
            ),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status atualizado.']);

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
            'lost_reason' => ['nullable', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
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
}
