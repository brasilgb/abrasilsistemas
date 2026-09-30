<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CRM-WA-29: verificação de cadastro pela API dos produtos. Desligada por padrão; ligar com
// PROSPECT_REGISTRATION_CHECK_ENABLED=true (container abrasilsistema-scheduler, schedule:work).
if (config('services.prospect_registration_check.enabled')) {
    Schedule::command('leads:check-registrations')
        ->hourly()
        ->withoutOverlapping(30);
}
