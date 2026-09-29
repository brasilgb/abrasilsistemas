<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo Lead → Produto/Serviço (CRM-WA-21). Roda depois de create_prospect_products.
     * `leads.product` (texto legado) continua existindo; nenhum dado é migrado aqui: a
     * associação dos leads antigos é feita depois, com `php artisan leads:link-prospect-products`.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('prospect_product_id')
                ->nullable()
                ->after('product')
                ->index()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['prospect_product_id']);
            $table->dropIndex(['prospect_product_id']);
            $table->dropColumn('prospect_product_id');
        });
    }
};
