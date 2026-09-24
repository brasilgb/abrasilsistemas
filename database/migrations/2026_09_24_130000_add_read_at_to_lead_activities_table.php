<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leitura interna no CRM das mensagens WhatsApp recebidas (inbound): nulo = não lida.
     * Independente de message_status, que guarda só os ACKs de entrega do WhatsApp.
     */
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('message_status');
            $table->index(['lead_id', 'direction', 'read_at'], 'lead_activities_lead_unread_index');
        });

        // Mensagens recebidas antes deste controle não geram alerta de não lida.
        DB::table('lead_activities')
            ->where('type', 'whatsapp')
            ->where('direction', 'inbound')
            ->update(['read_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex('lead_activities_lead_unread_index');
            $table->dropColumn('read_at');
        });
    }
};
