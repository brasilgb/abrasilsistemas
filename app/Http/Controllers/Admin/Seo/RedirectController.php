<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoChangeLog;
use App\Models\SeoRedirect;
use App\Services\Seo\RedirectManager;
use App\Services\Seo\SeoChangeLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Redirecionamentos. A exclusão é permitida, mas o painel recomenda desativar: o
 * histórico preserva links externos antigos e o registro de alterações fica guardado.
 */
class RedirectController extends Controller
{
    public function __construct(private RedirectManager $manager) {}

    public function index(Request $request): Response
    {
        $search = $request->string('busca')->trim()->toString();
        $map = $this->manager->map();

        $redirects = SeoRedirect::query()
            ->with(['creator:id,name', 'editor:id,name'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('source_path', 'like', "%{$search}%")->orWhere('target_url', 'like', "%{$search}%")))
            ->when($request->string('situacao')->toString() === 'inativos', fn ($query) => $query->where('is_active', false))
            ->when($request->string('situacao')->toString() === 'ativos', fn ($query) => $query->where('is_active', true))
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString()
            ->through(function (SeoRedirect $redirect) {
                $chain = $redirect->is_active && $redirect->status_code !== 410 ? $this->manager->chain($redirect->source_path, $redirect->target_url, $redirect->id) : ['hops' => [], 'loop' => false];

                return [
                    ...$redirect->only(['id', 'source_path', 'target_url', 'status_code', 'is_active', 'notes', 'hits', 'last_hit_at', 'created_at', 'updated_at']),
                    'creator' => $redirect->creator?->name,
                    'editor' => $redirect->editor?->name,
                    'problem' => $chain['loop'] ? 'Loop' : ($chain['hops'] !== [] ? 'Cadeia' : null),
                ];
            });

        return Inertia::render('admin/seo/redirects/index', [
            'redirects' => $redirects,
            'filters' => ['busca' => $search, 'situacao' => $request->string('situacao')->toString() ?: 'todos'],
            'statusCodes' => SeoRedirect::STATUS_CODES,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $request->string('origem')->toString());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $redirect = SeoRedirect::query()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->manager->flush();
        SeoChangeLogger::log('redirect', $redirect->id, 'created', $redirect->only(['source_path', 'target_url', 'status_code', 'is_active']), $request->user()->id);
        $this->flashWarnings($data);

        return to_route('admin.seo.redirects.index');
    }

    public function edit(SeoRedirect $redirect): Response
    {
        return $this->form($redirect);
    }

    public function update(Request $request, SeoRedirect $redirect): RedirectResponse
    {
        $data = $this->validated($request, $redirect);
        $before = $redirect->only(['source_path', 'target_url', 'status_code', 'is_active', 'notes']);
        $redirect->update([...$data, 'updated_by' => $request->user()->id]);
        $this->manager->flush();

        $changes = collect($redirect->only(array_keys($before)))->filter(fn ($value, $key) => $before[$key] !== $value)->map(fn ($value, $key) => ['from' => $before[$key], 'to' => $value])->all();
        SeoChangeLogger::log('redirect', $redirect->id, 'updated', $changes, $request->user()->id);
        $this->flashWarnings($data);

        return to_route('admin.seo.redirects.index');
    }

    public function destroy(Request $request, SeoRedirect $redirect): RedirectResponse
    {
        SeoChangeLogger::log('redirect', $redirect->id, 'deleted', $redirect->only(['source_path', 'target_url', 'status_code', 'hits']), $request->user()->id);
        $redirect->delete();
        $this->manager->flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Redirecionamento excluído.']);

        return to_route('admin.seo.redirects.index');
    }

    private function form(?SeoRedirect $redirect, string $source = ''): Response
    {
        return Inertia::render('admin/seo/redirects/form', [
            'redirect' => $redirect?->only(['id', 'source_path', 'target_url', 'status_code', 'is_active', 'notes', 'hits', 'last_hit_at']),
            'initialSource' => $source !== '' ? RedirectManager::normalizeSource($source) : null,
            'statusCodes' => SeoRedirect::STATUS_CODES,
            'history' => $redirect ? SeoChangeLog::query()->with('user:id,name')->where('entity_type', 'redirect')->where('entity_id', (string) $redirect->id)->latest('id')->limit(20)->get()
                ->map(fn (SeoChangeLog $log) => [...$log->only(['id', 'action', 'changes', 'created_at']), 'user' => $log->user?->name]) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SeoRedirect $redirect = null): array
    {
        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:512'],
            'target_url' => ['nullable', 'string', 'max:2048', Rule::requiredIf(fn () => (int) $request->input('status_code') !== 410)],
            'status_code' => ['required', 'integer', Rule::in(array_keys(SeoRedirect::STATUS_CODES))],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $check = $this->manager->validate($data['source_path'], $data['target_url'] ?? null, (int) $data['status_code'], $redirect?->id);

        if (SeoRedirect::query()->where('source_path', $check['source'])->when($redirect, fn ($query) => $query->whereKeyNot($redirect->id))->exists()) {
            $check['errors']['source_path'] = 'Já existe um redirecionamento para essa origem.';
        }

        // Regras inativas não entram no mapa: loop só importa se a regra for ficar ativa.
        if (! ($data['is_active'] ?? true)) {
            unset($check['errors']['target_url']);
            $check['warnings'] = [];

            if ((int) $data['status_code'] !== 410 && $check['target'] === null) {
                $check['errors']['target_url'] = 'Informe um caminho interno (/pagina) ou uma URL http(s) completa.';
            }
        }

        if ($check['errors'] !== []) {
            throw ValidationException::withMessages($check['errors']);
        }

        return [
            'source_path' => $check['source'],
            'target_url' => $check['target'],
            'status_code' => (int) $data['status_code'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
            'warnings' => $check['warnings'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function flashWarnings(array &$data): void
    {
        $warnings = $data['warnings'] ?? [];
        unset($data['warnings']);

        Inertia::flash('toast', $warnings === []
            ? ['type' => 'success', 'message' => 'Redirecionamento salvo.']
            : ['type' => 'warning', 'message' => 'Salvo com aviso: '.implode(' ', $warnings)]);
    }
}
