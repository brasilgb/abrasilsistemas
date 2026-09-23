<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('description');
            $table->string('message_status', 50)->nullable()->after('provider_message_id');
            $table->index(['lead_id', 'provider_message_id'], 'lead_activities_lead_provider_message_index');
        });
    }

    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex('lead_activities_lead_provider_message_index');
            $table->dropColumn(['provider_message_id', 'message_status']);
        });
    }
};
