<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('whatsapp_text')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->text('trial_expiration_text')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_products');
    }
};
