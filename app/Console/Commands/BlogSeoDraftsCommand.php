<?php

namespace App\Console\Commands;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\SeoMeta;
use App\Models\User;
use App\Services\Seo\ContentAnalyzer;
use App\Services\Seo\SeoMetaStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cria as pautas de SEO do blog (database/data/blog-seo-drafts.php) como RASCUNHO.
 * Nunca publica. Pula a pauta quando já existe artigo com o mesmo slug, título parecido
 * ou a mesma palavra-chave principal, para não duplicar nem canibalizar conteúdo.
 */
class BlogSeoDraftsCommand extends Command
{
    /** Semelhança de título (similar_text, %) a partir da qual a pauta é considerada repetida. */
    private const TITLE_SIMILARITY = 70.0;

    protected $signature = 'blog:seo-drafts {--dry-run : Só mostra o que seria criado}';

    protected $description = 'Cria as pautas de SEO do blog como rascunho, sem duplicar artigos existentes';

    public function handle(SeoMetaStore $store, ContentAnalyzer $analyzer): int
    {
        /** @var list<array{slug: string, title: string, excerpt: string, focus_keyword: string, secondary_keywords: list<string>, body: string}> $drafts */
        $drafts = require database_path('data/blog-seo-drafts.php');
        $author = User::query()->where('role', 'admin')->orderBy('id')->first();
        $category = BlogCategory::query()->where('slug', 'seo-e-performance')->first();

        if ($author === null) {
            $this->error('Nenhum usuário admin encontrado para ser o autor dos rascunhos.');

            return self::FAILURE;
        }

        if ($category === null) {
            $this->warn('Categoria "SEO e performance" não encontrada (rode php artisan migrate). Os rascunhos ficarão sem categoria.');
        }

        $existing = BlogPost::query()->get(['id', 'title', 'slug']);
        $keywords = SeoMeta::query()->where('subject_type', SeoMeta::TYPE_POST)->whereNotNull('focus_keyword')->pluck('focus_keyword', 'subject_id');
        $created = 0;

        foreach ($drafts as $draft) {
            $reason = $this->duplicateReason($draft, $existing, $keywords->all());

            if ($reason !== null) {
                $this->line("Pulado: {$draft['title']} ({$reason})");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Seria criado: {$draft['title']}");

                continue;
            }

            $post = BlogPost::query()->create([
                'user_id' => $author->id,
                'blog_category_id' => $category?->id,
                'title' => $draft['title'],
                'slug' => $draft['slug'],
                'excerpt' => $draft['excerpt'],
                'body' => $draft['body'],
                'status' => 'draft',
                'published_at' => null,
                'featured' => false,
            ]);

            $seo = [
                'meta_description' => $draft['excerpt'],
                'focus_keyword' => $draft['focus_keyword'],
                'secondary_keywords' => $draft['secondary_keywords'],
                'search_intent' => 'seo',
                'related_pages' => ['page:servico:seo-tecnico-e-otimizacao-de-sites'],
            ];
            $analysis = $analyzer->analyze([...$seo, 'title' => $draft['title'], 'excerpt' => $draft['excerpt'], 'slug' => $draft['slug'], 'body' => $draft['body'], 'post_id' => $post->id]);
            $store->save(SeoMeta::TYPE_POST, $post->id, $seo, $author->id, $analysis['score']);

            $existing->push($post);
            $created++;
            $this->info("Rascunho criado: {$draft['title']} (avaliação editorial {$analysis['score']}/100)");
        }

        $this->line($this->option('dry-run') ? 'Simulação concluída. Nada foi gravado.' : "{$created} rascunho(s) criado(s). Revise e publique pelo painel (Blog > Artigos).");

        return self::SUCCESS;
    }

    /**
     * @param  array{slug: string, title: string, focus_keyword: string}  $draft
     * @param  iterable<BlogPost>  $existing
     * @param  array<int|string, string>  $keywords
     */
    private function duplicateReason(array $draft, iterable $existing, array $keywords): ?string
    {
        $normalize = fn (string $text) => Str::of($text)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();
        $title = $normalize($draft['title']);

        foreach ($existing as $post) {
            if ($post->slug === $draft['slug']) {
                return 'já existe um artigo com o mesmo endereço';
            }

            similar_text($title, $normalize($post->title), $percent);

            if ($percent >= self::TITLE_SIMILARITY) {
                return "título parecido com \"{$post->title}\"";
            }
        }

        foreach ($keywords as $keyword) {
            if ($normalize($keyword) === $normalize($draft['focus_keyword'])) {
                return "a palavra-chave \"{$draft['focus_keyword']}\" já é de outro artigo";
            }
        }

        return null;
    }
}
