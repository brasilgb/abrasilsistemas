<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O próximo contato passa a ter horário (CRM-WA-08): "Atrasado" é quando o horário já passou.
 *
 * As datas antigas (sem horário) viram 09:00 no fuso comercial, e não meia-noite em UTC, que
 * no Brasil cairia na véspera às 21:00. Vale para leads e para o follow-up gravado nas atividades.
 */
return new class extends Migration
{
    private const TABLES = ['leads', 'lead_activities'];

    private const TIMEZONE = 'America/Sao_Paulo';

    private const LEGACY_TIME = '09:00';

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dateTime('next_follow_up_at')->nullable()->change();
            });

            DB::table($table)->whereNotNull('next_follow_up_at')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $date = substr((string) $row->next_follow_up_at, 0, 10);

                    DB::table($table)->where('id', $row->id)->update([
                        'next_follow_up_at' => Carbon::parse($date.' '.self::LEGACY_TIME, self::TIMEZONE)
                            ->setTimezone(config('app.timezone'))
                            ->format('Y-m-d H:i:s'),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->whereNotNull('next_follow_up_at')->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'next_follow_up_at' => Carbon::parse($row->next_follow_up_at, config('app.timezone'))
                            ->setTimezone(self::TIMEZONE)
                            ->format('Y-m-d 00:00:00'),
                    ]);
                }
            });

            Schema::table($table, function (Blueprint $table) {
                $table->date('next_follow_up_at')->nullable()->change();
            });
        }
    }
};
