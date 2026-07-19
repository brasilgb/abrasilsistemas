<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index('email', 'leads_email_index');
            $table->index('whatsapp', 'leads_whatsapp_index');
            $table->index(['company_name', 'city', 'state'], 'leads_company_location_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_email_index');
            $table->dropIndex('leads_whatsapp_index');
            $table->dropIndex('leads_company_location_index');
        });
    }
};
