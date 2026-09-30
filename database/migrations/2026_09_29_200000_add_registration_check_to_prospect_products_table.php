<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acompanhamento de cadastro por produto (CRM-WA-29). manual = só as ações do lead (padrão,
     * comportamento de antes); api = o CRM consulta a URL do produto (POST JSON, sempre HTTPS)
     * pelo WhatsApp ou e-mail do lead. O token é gravado cifrado (cast encrypted, como Setting).
     */
    public function up(): void
    {
        Schema::table('prospect_products', function (Blueprint $table) {
            $table->string('registration_check_mode', 10)->default('manual');
            $table->string('registration_api_url', 2048)->nullable();
            $table->string('registration_lookup_field', 20)->nullable();
            $table->text('registration_api_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prospect_products', function (Blueprint $table) {
            $table->dropColumn(['registration_check_mode', 'registration_api_url', 'registration_lookup_field', 'registration_api_token']);
        });
    }
};
