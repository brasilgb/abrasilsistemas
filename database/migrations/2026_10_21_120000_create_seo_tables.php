<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo de SEO (ABRASIL-SEO-02). Só cria tabelas novas e cada uma é
 * protegida por hasTable, então a migration pode rodar de novo sem erro.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Metadados por página: subject_type "page" (chave do PageRegistry) ou "post" (id do artigo).
        if (! Schema::hasTable('seo_metas')) {
            Schema::create('seo_metas', function (Blueprint $table) {
                $table->id();
                $table->string('subject_type', 20);
                $table->string('subject_id', 120);
                $table->string('meta_title', 255)->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->string('canonical_url', 2048)->nullable();
                $table->boolean('robots_index')->nullable();
                $table->boolean('robots_follow')->nullable();
                $table->string('og_title', 255)->nullable();
                $table->string('og_description', 500)->nullable();
                $table->string('og_image_url', 2048)->nullable();
                $table->string('og_image_alt', 255)->nullable();
                $table->string('twitter_title', 255)->nullable();
                $table->string('twitter_description', 500)->nullable();
                $table->string('focus_keyword', 120)->nullable();
                $table->json('secondary_keywords')->nullable();
                $table->string('search_intent', 40)->nullable();
                $table->json('related_pages')->nullable();
                $table->unsignedTinyInteger('editorial_score')->nullable();
                $table->timestamps();

                $table->unique(['subject_type', 'subject_id']);
                $table->index('focus_keyword');
            });
        }

        if (! Schema::hasTable('seo_redirects')) {
            Schema::create('seo_redirects', function (Blueprint $table) {
                $table->id();
                $table->string('source_path', 512)->unique();
                $table->string('target_url', 2048)->nullable();
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->boolean('is_active')->default(true);
                $table->string('notes', 500)->nullable();
                $table->unsignedInteger('hits')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'status_code']);
            });
        }

        if (! Schema::hasTable('seo_change_logs')) {
            Schema::create('seo_change_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 40);
                $table->string('entity_id', 120)->nullable();
                $table->string('action', 40);
                $table->json('changes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('seo_audits')) {
            Schema::create('seo_audits', function (Blueprint $table) {
                $table->id();
                $table->string('status', 20)->default('queued');
                $table->string('trigger', 20)->default('manual');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('pages_total')->default(0);
                $table->unsignedInteger('pages_scanned')->default(0);
                $table->unsignedInteger('critical_count')->default(0);
                $table->unsignedInteger('warning_count')->default(0);
                $table->unsignedInteger('opportunity_count')->default(0);
                $table->json('summary')->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seo_audit_pages')) {
            Schema::create('seo_audit_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_audit_id')->constrained()->cascadeOnDelete();
                $table->string('url', 2048);
                $table->string('subject', 140)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->string('final_url', 2048)->nullable();
                $table->string('title', 500)->nullable();
                $table->string('description', 1000)->nullable();
                $table->string('canonical', 2048)->nullable();
                $table->string('robots', 255)->nullable();
                $table->boolean('indexable')->default(false);
                $table->boolean('in_sitemap')->default(false);
                $table->unsignedSmallInteger('h1_count')->nullable();
                $table->unsignedSmallInteger('images_without_alt')->default(0);
                $table->unsignedSmallInteger('internal_links')->default(0);
                $table->unsignedSmallInteger('inbound_links')->default(0);
                $table->unsignedInteger('response_ms')->nullable();
                $table->unsignedInteger('html_bytes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seo_audit_issues')) {
            Schema::create('seo_audit_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seo_audit_id')->constrained()->cascadeOnDelete();
                $table->string('url', 2048)->nullable();
                $table->string('subject', 140)->nullable();
                $table->string('severity', 20);
                $table->string('code', 60);
                $table->string('message', 500);
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['seo_audit_id', 'severity']);
                $table->index(['seo_audit_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_issues');
        Schema::dropIfExists('seo_audit_pages');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_change_logs');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_metas');
    }
};
