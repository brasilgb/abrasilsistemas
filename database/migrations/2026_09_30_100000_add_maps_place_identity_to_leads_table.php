<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identidade do lugar no Google Maps (CRM-LEAD-02), extraída de `maps_url` por MapsPlace:
     * Place ID e FTID indexados para a deduplicação, coordenadas do lugar e as checagens do site
     * feitas pela extensão. Os leads antigos são preenchidos por `leads:backfill-maps-place`.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Place ID diferencia maiúsculas; a collation padrão do MySQL (_ci) não.
            $table->string('google_place_id')->nullable()->after('maps_url')
                ->collation(Schema::getConnection()->getDriverName() === 'mysql' ? 'utf8mb4_bin' : null);

            $table->string('maps_feature_id', 64)->nullable()->after('google_place_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('maps_feature_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('website_checks')->nullable()->after('site_status');

            $table->index('google_place_id');
            $table->index('maps_feature_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['google_place_id']);
            $table->dropIndex(['maps_feature_id']);
            $table->dropColumn(['google_place_id', 'maps_feature_id', 'latitude', 'longitude', 'website_checks']);
        });
    }
};
