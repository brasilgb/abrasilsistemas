<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Categoria editorial de SEO (ABRASIL-SEO-FINAL-01). Só cria se ainda não existir uma
 * categoria de SEO (pelo slug ou pelo nome), para não duplicar uma criada pelo painel.
 */
return new class extends Migration
{
    private const SLUG = 'seo-e-performance';

    public function up(): void
    {
        $exists = DB::table('blog_categories')
            ->where('slug', self::SLUG)
            ->orWhere('slug', 'seo')
            ->orWhere('slug', 'like', 'seo-%')
            ->orWhere('name', 'SEO')
            ->orWhere('name', 'like', 'SEO %')
            ->orWhere('name', 'like', '% SEO')
            ->orWhere('name', 'like', '% SEO %')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('blog_categories')->insert([
            'name' => 'SEO e performance',
            'slug' => self::SLUG,
            'description' => 'Como fazer o site da empresa ser encontrado nas buscas: SEO técnico, desempenho, indexação e conteúdo.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('blog_categories')
            ->where('slug', self::SLUG)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('blog_posts')->whereColumn('blog_posts.blog_category_id', 'blog_categories.id'))
            ->delete();
    }
};
