<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Captação comercial (ABRASIL-COMERCIAL-01): origem da visita (UTM, site de indicação e página de
 * entrada), consentimento para comunicações e empresa opcional no formulário do site.
 *
 * Só acrescenta colunas e afrouxa a obrigatoriedade de company_name. Registros antigos ficam com
 * as colunas novas em NULL ("não identificada").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('company_name')->nullable()->change();
            $table->string('entry_page')->nullable()->after('source_page');
            $table->string('referrer_host', 150)->nullable()->after('entry_page');
            $table->string('utm_source', 100)->nullable()->index()->after('referrer_host');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->string('utm_term', 100)->nullable()->after('utm_campaign');
            $table->string('utm_content', 100)->nullable()->after('utm_term');
            $table->timestamp('marketing_consent_at')->nullable()->after('utm_content');
        });
    }

    public function down(): void
    {
        // A coluna volta a ser obrigatória: quem não informou empresa fica com o próprio nome.
        DB::table('leads')->whereNull('company_name')->update(['company_name' => DB::raw("COALESCE(contact_name, 'Não informada')")]);

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['utm_source']);
            $table->dropColumn(['entry_page', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'marketing_consent_at']);
            $table->string('company_name')->nullable(false)->change();
        });
    }
};
