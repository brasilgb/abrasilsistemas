<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Última verificação de cadastro pela API do produto (CRM-WA-29): quando, o resultado
     * (registered, not_registered, error) e o código do erro. Não é estado comercial: só serve
     * para a tela e para espaçar as consultas. O cadastro confirmado vira o trial de sempre.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dateTime('registration_checked_at')->nullable()->index();
            $table->string('registration_check_status', 20)->nullable();
            $table->string('registration_check_error', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['registration_checked_at']);
            $table->dropColumn(['registration_checked_at', 'registration_check_status', 'registration_check_error']);
        });
    }
};
