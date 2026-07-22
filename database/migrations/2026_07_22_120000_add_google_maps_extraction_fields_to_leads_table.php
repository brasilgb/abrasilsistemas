<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('company_name');
            $table->string('category')->nullable()->after('industry');
            $table->boolean('has_website')->default(false)->after('website');
            $table->string('site_status')->nullable()->after('has_website');
            $table->boolean('can_improve')->default(false)->after('site_status');
            $table->text('opportunity')->nullable()->after('can_improve');
            $table->string('maps_url', 2048)->nullable()->after('opportunity');
            $table->decimal('rating', 3, 2)->nullable()->after('maps_url');
            $table->unsignedInteger('reviews')->nullable()->after('rating');
            $table->timestamp('captured_at')->nullable()->after('reviews');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'category',
                'has_website',
                'site_status',
                'can_improve',
                'opportunity',
                'maps_url',
                'rating',
                'reviews',
                'captured_at',
            ]);
        });
    }
};
