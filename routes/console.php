<?php

use App\Services\Seo\SeoSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auditoria de SEO agendada (frequência em Painel → SEO → Configurações; "off" desliga).
Schedule::command('seo:audit --trigger=scheduled')
    ->dailyAt('04:30')
    ->when(function () {
        $schedule = app(SeoSettings::class)->get('audit_schedule');

        return $schedule === 'daily' || ($schedule === 'weekly' && now()->isMonday());
    })
    ->withoutOverlapping();
