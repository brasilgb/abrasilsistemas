<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trava de idempotência das futuras automações (CRM-WA-18): no máximo uma reserva por
     * lead + automation_key, garantida pelo UNIQUE do banco. Soft delete do lead preserva as
     * reservas; exclusão definitiva as remove junto (mesmo padrão de lead_activities).
     */
    public function up(): void
    {
        Schema::create('lead_automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $key = $table->string('automation_key', 150);
            $table->timestamp('executed_at');

            // A chave só aceita ASCII (validada na API). No MySQL a comparação precisa ser binária:
            // com a collation padrão (case-insensitive) "A" e "a" colidiriam no UNIQUE.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $key->charset('ascii')->collation('ascii_bin');
            }

            $table->unique(['lead_id', 'automation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_automation_executions');
    }
};
