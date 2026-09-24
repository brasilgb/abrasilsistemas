<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Direção da mensagem WhatsApp (inbound/outbound). Nulo para atividades que não são
     * mensagens (notas, ligações, registros manuais) e para o legado anterior a esta coluna.
     */
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->string('direction', 10)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
