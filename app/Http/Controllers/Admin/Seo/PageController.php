<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\SeoMetaStore;
use App\Services\Seo\SeoSettings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(Request $request, PageRegistry $registry): Response
    {
        $pages = $registry->withEffective();
        $titles = $pages->where('indexable', true)->countBy(fn ($page) => Str::lower($page['effectiveTitle']));
        $descriptions = $pages->where('indexable', true)->filter(fn ($page) => $page['effectiveDescription'] !== '')->countBy(fn ($page) => Str::lower($page['effectiveDescription']));

        return Inertia::render('admin/seo/pages/index', [
            'pages' => $pages->map(fn (array $page) => [
                ...collect($page)->only(['key', 'type', 'label', 'group', 'path', 'url', 'effectiveTitle', 'effectiveDescription', 'effectiveRobots', 'effectiveCanonical', 'indexable', 'canonicalElsewhere', 'editUrl', 'focusKeyword'])->all(),
                'customized' => $page['meta'] !== null,
                'score' => $page['meta']?->editorial_score,
                'flags' => array_values(array_filter([
                    $page['effectiveDescription'] === '' ? 'Sem descrição' : null,
                    $page['indexable'] && ($titles[Str::lower($page['effectiveTitle'])] ?? 0) > 1 ? 'Título repetido' : null,
                    $page['indexable'] && $page['effectiveDescription'] !== '' && ($descriptions[Str::lower($page['effectiveDescription'])] ?? 0) > 1 ? 'Descrição repetida' : null,
                    mb_strlen($page['effectiveTitle']) > 65 ? 'Título longo' : null,
                    mb_strlen($page['effectiveDescription']) > 160 ? 'Descrição longa' : null,
                ])),
            ])->values(),
            'filters' => ['grupo' => $request->string('grupo')->toString() ?: null],
        ]);
    }

    public function edit(string $key, PageRegistry $registry, SeoSettings $settings): Response|RedirectResponse
    {
        $page = $registry->find($key);
        abort_if($page === null, 404);

        // O SEO do artigo é editado no próprio formulário do artigo, junto com a análise editorial.
        if ($page['type'] === SeoMeta::TYPE_POST) {
            return redirect($page['editUrl']);
        }

        $effective = $registry->withEffective(collect([$page]))->first();

        return Inertia::render('admin/seo/pages/edit', [
            'page' => collect($effective)->except('meta')->all(),
            'meta' => SeoMeta::for($page['type'], $page['id']),
            'defaults' => [
                'title' => $page['title'],
                'description' => $page['description'],
                'robots' => $page['robots'],
                'canonical' => $settings->url($page['path']),
                'ogImage' => $settings->absolute($page['ogImage'] ?? $settings->get('social_image_url')),
            ],
            'pageOptions' => $registry->all()->map(fn ($item) => ['key' => $item['key'], 'label' => $item['label'], 'group' => $item['group']])->reject(fn ($item) => $item['key'] === $key)->values(),
            'intents' => SeoMeta::INTENTS,
            'siteName' => $settings->get('company_name'),
        ]);
    }

    public function update(Request $request, string $key, PageRegistry $registry, SeoMetaStore $store): RedirectResponse
    {
        $page = $registry->find($key);
        abort_if($page === null || $page['type'] !== SeoMeta::TYPE_PAGE, 404);

        $data = $request->validate(SeoMetaStore::rules());
        $store->save($page['type'], $page['id'], $data, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Metadados salvos.'.(Seo::isIndexable(Seo::robots($page['robots'], $data['robots_index'] ?? null, $data['robots_follow'] ?? null)) ? '' : ' A página está como noindex e saiu do sitemap.')]);

        return to_route('admin.seo.pages.edit', $key);
    }
}
