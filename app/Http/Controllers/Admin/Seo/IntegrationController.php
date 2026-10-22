<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\GoogleIntegration;
use App\Services\Seo\IndexNow;
use App\Services\Seo\SeoChangeLogger;
use App\Services\Seo\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Integrações oficiais: Google Search Console e GA4 (OAuth, só leitura), verificação do
 * Bing e IndexNow. Sem conexão, nenhum número é exibido: só o estado e o que falta.
 */
class IntegrationController extends Controller
{
    public const PERIODS = [7, 28, 90];

    public function index(Request $request, SeoSettings $settings, GoogleIntegration $google, IndexNow $indexNow): Response
    {
        $days = in_array((int) $request->query('dias'), self::PERIODS, true) ? (int) $request->query('dias') : 28;
        $property = (string) $settings->get('gsc_property');
        $ga4Property = (string) $settings->get('ga4_property_id');
        $connected = $google->connected();

        return Inertia::render('admin/seo/integrations', [
            'google' => $google->status(),
            'redirectUri' => $google->redirectUri(),
            'settings' => collect($settings->all())->only(['google_verification', 'bing_verification', 'ga4_measurement_id', 'ga4_property_id', 'ga4_events', 'gsc_property', 'indexnow_enabled'])->all(),
            'indexNow' => ['enabled' => $indexNow->enabled(), 'keyUrl' => $indexNow->enabled() ? $settings->url('/'.$settings->get('indexnow_key').'.txt') : null],
            'ga4Events' => SeoSettings::GA4_EVENTS,
            'days' => $days,
            'periods' => self::PERIODS,
            'delayDays' => GoogleIntegration::GSC_DELAY_DAYS,
            'sites' => Inertia::defer(fn () => $connected ? $this->attempt(fn () => $google->sites()) : null),
            'searchConsole' => Inertia::defer(fn () => $connected && $property !== '' ? $this->attempt(fn () => $google->searchPerformance($property, $days)) : null),
            'analytics' => Inertia::defer(fn () => $connected && $ga4Property !== '' ? $this->attempt(fn () => $google->analyticsReport($ga4Property, $days, $settings->ga4Events())) : null),
        ]);
    }

    public function update(Request $request, SeoSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'google_verification' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]{10,100}$/'],
            'bing_verification' => ['nullable', 'string', 'max:64', 'regex:/^[A-Fa-f0-9]{16,64}$/'],
            'ga4_measurement_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,16}$/'],
            'ga4_property_id' => ['nullable', 'string', 'regex:/^[0-9]{5,20}$/'],
            'ga4_events' => ['nullable', 'array'],
            'ga4_events.*' => [Rule::in(array_keys(SeoSettings::GA4_EVENTS))],
            'gsc_property' => ['nullable', 'string', 'max:255', 'regex:#^(sc-domain:[a-z0-9.-]+|https?://[^\s]+/)$#'],
            'indexnow_enabled' => ['boolean'],
        ], [
            'google_verification.regex' => 'Cole só o valor do atributo content da meta tag do Google.',
            'bing_verification.regex' => 'Cole só o valor do atributo content da meta tag msvalidate.01.',
            'ga4_measurement_id.regex' => 'O ID de métricas do GA4 tem o formato G-XXXXXXX.',
            'gsc_property.regex' => 'Use a propriedade como aparece no Search Console (sc-domain:dominio ou https://dominio/).',
        ]);

        $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data);
        $data['ga4_events'] = array_values($data['ga4_events'] ?? []);

        // Uma chave nova a cada ativação; desativar não apaga a chave atual do arquivo servido.
        if (($data['indexnow_enabled'] ?? false) && ! preg_match('/^[a-f0-9]{32}$/', (string) $settings->get('indexnow_key'))) {
            $data['indexnow_key'] = bin2hex(random_bytes(16));
        }

        $changes = $settings->save(array_map(fn ($value) => $value ?? '', $data));

        if ($changes !== []) {
            SeoChangeLogger::log('integrations', null, 'updated', $changes, $request->user()->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Integrações salvas.']);

        return to_route('admin.seo.integrations');
    }

    public function connect(Request $request, GoogleIntegration $google): RedirectResponse
    {
        if (! $google->configured()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Configure GOOGLE_SEARCH_CONSOLE_CLIENT_ID e GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET no servidor antes de conectar.']);

            return to_route('admin.seo.integrations');
        }

        $state = Str::random(40);
        $request->session()->put('seo.google.state', $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleIntegration $google): RedirectResponse
    {
        $expected = (string) $request->session()->pull('seo.google.state');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state'))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'A autorização expirou ou não foi iniciada por aqui. Tente conectar de novo.']);

            return to_route('admin.seo.integrations');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'A conexão com o Google foi cancelada.']);

            return to_route('admin.seo.integrations');
        }

        try {
            $google->exchangeCode((string) $request->query('code'));
            SeoChangeLogger::log('integrations', 'google', 'connected', [], $request->user()->id);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Google conectado. Escolha a propriedade do Search Console.']);
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('admin.seo.integrations');
    }

    public function disconnect(Request $request, GoogleIntegration $google): RedirectResponse
    {
        $google->disconnect();
        SeoChangeLogger::log('integrations', 'google', 'disconnected', [], $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Google desconectado e autorização revogada.']);

        return to_route('admin.seo.integrations');
    }

    /**
     * @return array<string, mixed>
     */
    private function attempt(callable $callback): array
    {
        try {
            return ['data' => $callback(), 'error' => null];
        } catch (RuntimeException $e) {
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }
}
