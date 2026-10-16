<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Área "Solicitações" (ABRASIL-WEB-03): o site deixa de ser CRM e passa a acompanhar só os
 * contatos do formulário (`source = site`). A situação da solicitação fica numa coluna
 * própria, para não misturar com o status comercial (`status`) do histórico, que é mantido.
 *
 * Só acrescenta colunas: nenhum dado é apagado. O down() remove apenas as duas colunas novas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('request_status', 20)->nullable()->index()->after('status');
            $table->string('source_page')->nullable()->after('source');
        });

        // Solicitações já recebidas: situação inicial a partir do status comercial que tinham.
        $mapping = [
            'new' => 'new',
            'contacted' => 'in_progress',
            'interested' => 'in_progress',
            'meeting' => 'in_progress',
            'converted' => 'done',
            'lost' => 'archived',
        ];

        foreach ($mapping as $status => $requestStatus) {
            DB::table('leads')
                ->where('source', 'site')
                ->where('status', $status)
                ->whereNull('request_status')
                ->update(['request_status' => $requestStatus]);
        }

        DB::table('leads')
            ->where('source', 'site')
            ->whereNull('request_status')
            ->update(['request_status' => 'new']);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['request_status']);
            $table->dropColumn(['request_status', 'source_page']);
        });
    }
};
