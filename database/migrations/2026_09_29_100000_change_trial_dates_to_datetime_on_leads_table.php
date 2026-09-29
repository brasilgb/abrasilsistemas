<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fuso das datas de calendário gravadas pelo CRM-WA-16 (Lead::FOLLOW_UP_TIMEZONE). */
    private const TIMEZONE = 'America/Sao_Paulo';

    /**
     * Período de teste com horário (CRM-WA-23): trial_started_at e trial_ends_at passam a ser
     * instantes em UTC, e trial_ends_at é o vencimento (vencido quando trial_ends_at <= agora).
     * As datas do CRM-WA-16 mantêm o mesmo vencimento: início às 00:00 de São Paulo do dia
     * informado; término (último dia do teste) vira 00:00 de São Paulo do dia seguinte.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dateTime('trial_started_at')->nullable()->change();
            $table->dateTime('trial_ends_at')->nullable()->change();
        });

        $this->convert(fn (?string $startedAt, ?string $endsAt) => [
            'trial_started_at' => $startedAt === null ? null : $this->startOfDay($startedAt)->format('Y-m-d H:i:s'),
            'trial_ends_at' => $endsAt === null ? null : $this->startOfDay($endsAt)->addDay()->format('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        // Volta às datas de calendário: o término é o último dia em que o teste ainda valia.
        $this->convert(fn (?string $startedAt, ?string $endsAt) => [
            'trial_started_at' => $startedAt === null ? null : Carbon::parse($startedAt, 'UTC')->setTimezone(self::TIMEZONE)->toDateString(),
            'trial_ends_at' => $endsAt === null ? null : Carbon::parse($endsAt, 'UTC')->subSecond()->setTimezone(self::TIMEZONE)->toDateString(),
        ]);

        Schema::table('leads', function (Blueprint $table) {
            $table->date('trial_started_at')->nullable()->change();
            $table->date('trial_ends_at')->nullable()->change();
        });
    }

    private function startOfDay(string $value): Carbon
    {
        return Carbon::parse(substr($value, 0, 10), self::TIMEZONE)->startOfDay()->setTimezone('UTC');
    }

    /** @param  callable(?string, ?string): array<string, ?string>  $values */
    private function convert(callable $values): void
    {
        DB::table('leads')
            ->where(fn ($query) => $query->whereNotNull('trial_started_at')->orWhereNotNull('trial_ends_at'))
            ->orderBy('id')
            ->get(['id', 'trial_started_at', 'trial_ends_at'])
            ->each(fn (object $lead) => DB::table('leads')
                ->where('id', $lead->id)
                ->update($values($lead->trial_started_at, $lead->trial_ends_at)));
    }
};
