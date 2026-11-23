<?php

namespace App\Providers;

use App\Services\Seo\SeoSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uma leitura das configurações de SEO por requisição (ou job).
        $this->app->scoped(SeoSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimits();
    }

    /**
     * Formulário comercial: o visitante recebe a mensagem no próprio formulário, não uma página de erro 429.
     */
    protected function configureRateLimits(): void
    {
        $tooMany = fn () => back()->withErrors(['form' => 'Muitos envios em sequência. Aguarde alguns minutos e tente novamente.']);

        RateLimiter::for('site-contact', fn (Request $request) => [
            Limit::perMinute(5)->by('site-contact:m:'.$request->ip())->response($tooMany),
            Limit::perHour(20)->by('site-contact:h:'.$request->ip())->response($tooMany),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
