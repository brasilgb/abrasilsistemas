<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Período de teste do software no lead (CRM-WA-16). Datas de calendário (America/Sao_Paulo);
     * o estado ativo/vencido é derivado no model (Lead::TRIAL_STATES), sem novo status.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->date('trial_started_at')->nullable()->after('next_follow_up_at');
            $table->date('trial_ends_at')->nullable()->after('trial_started_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['trial_ends_at']);
            $table->dropColumn(['trial_started_at', 'trial_ends_at']);
        });
    }
};
