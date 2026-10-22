<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\StructuredData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dados estruturados gerados para cada página, com a validação interna. Os links levam
 * ao Teste de pesquisa aprimorada do Google e ao validador do schema.org, as ferramentas
 * oficiais para a conferência final.
 */
class StructuredDataController extends Controller
{
    public function __invoke(PageRegistry $registry, StructuredData $structuredData): Response
    {
        $pages = $registry->all(withPosts: false)->map(function (array $page) use ($structuredData, $registry) {
            $jsonLd = $structuredData->forPage($page['id']);

            return $this->row($page, $jsonLd, $registry);
        });

        $posts = BlogPost::query()->published()->with(['author:id,name', 'category:id,name,slug'])->latest('published_at')->limit(30)->get()
            ->map(fn (BlogPost $post) => $this->row($registry->postEntry($post), $structuredData->blogPosting($post), $registry));

        return Inertia::render('admin/seo/structured-data', [
            'pages' => $pages->concat($posts)->values(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $jsonLd
     * @return array<string, mixed>
     */
    private function row(array $page, array $jsonLd, PageRegistry $registry): array
    {
        $url = StructuredData::url($page['path']);

        return [
            'key' => $page['key'],
            'label' => $page['label'],
            'group' => $page['group'],
            'url' => $url,
            'types' => StructuredData::types($jsonLd),
            'issues' => $jsonLd === [] ? [] : StructuredData::validate($jsonLd),
            'json' => $jsonLd === [] ? null : json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'richResultsUrl' => 'https://search.google.com/test/rich-results?url='.rawurlencode($url),
            'schemaValidatorUrl' => 'https://validator.schema.org/#url='.rawurlencode($url),
        ];
    }
}
