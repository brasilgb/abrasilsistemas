<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensagem WhatsApp gerada pela execução da automação (CRM-WA-28). Nula até o /whatsapp/log
     * informar a automation_key: reserva sem envio confirmado continua sem vínculo. UNIQUE: uma
     * mensagem pertence a no máximo uma execução.
     */
    public function up(): void
    {
        Schema::table('lead_automation_executions', function (Blueprint $table) {
            $table->foreignId('lead_activity_id')->nullable()->unique()->constrained('lead_activities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_automation_executions', function (Blueprint $table) {
            $table->dropForeign(['lead_activity_id']);
            $table->dropUnique(['lead_activity_id']);
            $table->dropColumn('lead_activity_id');
        });
    }
};
