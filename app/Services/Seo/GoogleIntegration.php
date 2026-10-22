<?php

namespace App\Services\Seo;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Integração oficial com o Google por OAuth 2.0 (Search Console e Google Analytics 4,
 * só leitura). O client ID e o secret vêm do ambiente (GOOGLE_SEARCH_CONSOLE_CLIENT_*);
 * os tokens ficam criptografados na tabela settings e nunca vão para o navegador ou logs.
 * Sem conexão, o painel mostra o estado "não conectado" e nenhum número.
 */
class GoogleIntegration
{
    public const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const GSC_API = 'https://www.googleapis.com/webmasters/v3';

    private const GA4_API = 'https://analyticsdata.googleapis.com/v1beta';

    /** O Search Console publica os dados com cerca de 2 a 3 dias de atraso. */
    public const GSC_DELAY_DAYS = 3;

    public function __construct(private SeoSettings $settings) {}

    public function configured(): bool
    {
        return filled(config('seo.google.client_id')) && filled(config('seo.google.client_secret'));
    }

    public function connected(): bool
    {
        return filled($this->token()['refresh_token'] ?? null);
    }

    public function redirectUri(): string
    {
        return $this->settings->url('/admin/seo/integracoes/google/callback');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('seo.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): void
    {
        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('seo.google.client_id'),
            'client_secret' => config('seo.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful() || blank($response->json('refresh_token'))) {
            Log::warning('seo.google.oauth_failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new RuntimeException('O Google não autorizou a conexão. Tente novamente.');
        }

        $this->storeToken([
            'refresh_token' => $response->json('refresh_token'),
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60)->toAtomString(),
            'scope' => $response->json('scope'),
            'connected_at' => now()->toAtomString(),
        ]);
    }

    public function disconnect(): void
    {
        $token = $this->token();

        if (filled($token['refresh_token'] ?? null)) {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $token['refresh_token']]);
        }

        Setting::query()->where('key', Setting::SEO_GSC_TOKEN)->delete();
        Cache::forget('seo.google.sites');
    }

    /**
     * Estado público da conexão (sem tokens).
     *
     * @return array{configured: bool, connected: bool, connectedAt: string|null, scopes: list<string>}
     */
    public function status(): array
    {
        $token = $this->token();

        return [
            'configured' => $this->configured(),
            'connected' => $this->connected(),
            'connectedAt' => $token['connected_at'] ?? null,
            'scopes' => array_values(array_filter(explode(' ', (string) ($token['scope'] ?? '')))),
        ];
    }

    /**
     * Propriedades do Search Console a que a conta conectada tem acesso.
     *
     * @return list<array{siteUrl: string, permissionLevel: string}>
     */
    public function sites(): array
    {
        return Cache::remember('seo.google.sites', 600, fn () => array_values(array_map(
            fn (array $site) => ['siteUrl' => (string) $site['siteUrl'], 'permissionLevel' => (string) ($site['permissionLevel'] ?? '')],
            (array) ($this->get(self::GSC_API.'/sites')['siteEntry'] ?? []),
        )));
    }

    /**
     * Desempenho no Search Console: totais do período e do anterior, consultas e páginas.
     *
     * @return array<string, mixed>
     */
    public function searchPerformance(string $property, int $days): array
    {
        $end = now()->subDays(self::GSC_DELAY_DAYS)->startOfDay();
        $start = $end->copy()->subDays($days - 1);
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1);
        $key = 'seo.gsc.'.md5($property.$days.$end->toDateString());

        return Cache::remember($key, now()->addHours(6), function () use ($property, $start, $end, $previousStart, $previousEnd) {
            $url = self::GSC_API.'/sites/'.rawurlencode($property).'/searchAnalytics/query';
            $query = fn (CarbonInterface $from, CarbonInterface $to, array $dimensions = [], int $limit = 1) => $this->post($url, array_filter([
                'startDate' => $from->toDateString(),
                'endDate' => $to->toDateString(),
                'dimensions' => $dimensions ?: null,
                'rowLimit' => $limit,
                'dataState' => 'final',
            ]))['rows'] ?? [];

            $totals = fn (array $rows) => [
                'clicks' => (int) ($rows[0]['clicks'] ?? 0),
                'impressions' => (int) ($rows[0]['impressions'] ?? 0),
                'ctr' => round((float) ($rows[0]['ctr'] ?? 0) * 100, 2),
                'position' => round((float) ($rows[0]['position'] ?? 0), 1),
            ];
            $rows = fn (array $rows) => array_map(fn (array $row) => [
                'key' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'ctr' => round((float) $row['ctr'] * 100, 2),
                'position' => round((float) $row['position'], 1),
            ], $rows);

            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'previousPeriod' => ['start' => $previousStart->toDateString(), 'end' => $previousEnd->toDateString()],
                'current' => $totals($query($start, $end)),
                'previous' => $totals($query($previousStart, $previousEnd)),
                'queries' => $rows($query($start, $end, ['query'], 25)),
                'pages' => $rows($query($start, $end, ['page'], 25)),
                'fetchedAt' => now()->toAtomString(),
            ];
        });
    }

    /**
     * Tráfego e conversões do GA4 (Data API): sessões, usuários e os eventos comerciais.
     *
     * @param  list<string>  $events
     * @return array<string, mixed>
     */
    public function analyticsReport(string $propertyId, int $days, array $events): array
    {
        $key = 'seo.ga4.'.md5($propertyId.$days.implode(',', $events).now()->toDateString());

        return Cache::remember($key, now()->addHours(3), function () use ($propertyId, $days, $events) {
            $url = self::GA4_API.'/properties/'.rawurlencode($propertyId).':runReport';
            $ranges = [
                ['startDate' => $days.'daysAgo', 'endDate' => 'yesterday', 'name' => 'current'],
                ['startDate' => ($days * 2).'daysAgo', 'endDate' => ($days + 1).'daysAgo', 'name' => 'previous'],
            ];

            $traffic = $this->post($url, [
                'dateRanges' => $ranges,
                'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'engagementRate']],
                'dimensionFilter' => ['filter' => ['fieldName' => 'sessionDefaultChannelGroup', 'stringFilter' => ['value' => 'Organic Search']]],
            ]);
            $eventReport = $events === [] ? [] : $this->post($url, [
                'dateRanges' => $ranges,
                'dimensions' => [['name' => 'eventName']],
                'metrics' => [['name' => 'eventCount']],
                'dimensionFilter' => ['filter' => ['fieldName' => 'eventName', 'inListFilter' => ['values' => $events]]],
            ]);

            $metric = function (array $report, string $range, int $index): float {
                foreach ($report['rows'] ?? [] as $row) {
                    if (($row['dimensionValues'][count($row['dimensionValues']) - 1]['value'] ?? '') === $range) {
                        return (float) ($row['metricValues'][$index]['value'] ?? 0);
                    }
                }

                return 0.0;
            };

            $eventCounts = [];

            foreach ($eventReport['rows'] ?? [] as $row) {
                $name = $row['dimensionValues'][0]['value'] ?? '';
                $range = $row['dimensionValues'][1]['value'] ?? 'current';
                $eventCounts[$name][$range] = (int) ($row['metricValues'][0]['value'] ?? 0);
            }

            return [
                'organic' => [
                    'current' => ['sessions' => (int) $metric($traffic, 'current', 0), 'users' => (int) $metric($traffic, 'current', 1), 'engagementRate' => round($metric($traffic, 'current', 2) * 100, 1)],
                    'previous' => ['sessions' => (int) $metric($traffic, 'previous', 0), 'users' => (int) $metric($traffic, 'previous', 1), 'engagementRate' => round($metric($traffic, 'previous', 2) * 100, 1)],
                ],
                'events' => array_map(fn (string $event) => ['name' => $event, 'current' => $eventCounts[$event]['current'] ?? 0, 'previous' => $eventCounts[$event]['previous'] ?? 0], $events),
                'fetchedAt' => now()->toAtomString(),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        $response = Http::withToken($this->accessToken())->timeout(20)->acceptJson()->get($url);

        return $this->decode($response);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $url, array $body): array
    {
        $response = Http::withToken($this->accessToken())->timeout(20)->acceptJson()->post($url, $body);

        return $this->decode($response);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            Log::warning('seo.google.api_error', ['status' => $response->status(), 'reason' => $response->json('error.status')]);

            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'A conta conectada não tem acesso a essa propriedade (ou a autorização foi revogada).',
                429 => 'Limite de consultas da API do Google atingido. Tente novamente mais tarde.',
                default => 'A API do Google respondeu com erro ('.$response->status().').',
            });
        }

        return (array) $response->json();
    }

    private function accessToken(): string
    {
        $token = $this->token();

        if (blank($token['refresh_token'] ?? null)) {
            throw new RuntimeException('Google não conectado.');
        }

        if (filled($token['access_token'] ?? null) && now()->lt(Carbon::parse($token['expires_at'] ?? 'now'))) {
            return (string) $token['access_token'];
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('seo.google.client_id'),
            'client_secret' => config('seo.google.client_secret'),
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::warning('seo.google.refresh_failed', ['status' => $response->status(), 'error' => $response->json('error')]);

            throw new RuntimeException('A autorização do Google expirou ou foi revogada. Conecte novamente.');
        }

        $this->storeToken([...$token, 'access_token' => $response->json('access_token'), 'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60)->toAtomString()]);

        return (string) $response->json('access_token');
    }

    /** @return array<string, mixed> */
    private function token(): array
    {
        $decoded = json_decode((string) Setting::valueFor(Setting::SEO_GSC_TOKEN, ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $token */
    private function storeToken(array $token): void
    {
        Setting::query()->updateOrCreate(['key' => Setting::SEO_GSC_TOKEN], ['value' => json_encode($token)]);
    }
}
