<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\Seo\PageRegistry;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planejamento editorial por intenção de busca (ABRASIL-SEO-02 §13). As palavras-chave
 * servem para organizar conteúdo e links internos; o painel não mostra volume de busca
 * nem posição (esses dados só existem com o Search Console conectado).
 */
class KeywordController extends Controller
{
    /** Intenção padrão das páginas comerciais, quando o painel não definiu outra. */
    public const DEFAULT_INTENTS = [
        'page:servico:desenvolvimento-de-sistemas' => 'desenvolvimento',
        'page:servico:automacao-de-processos' => 'automacao',
        'page:servico:integracao-de-sistemas-e-apis' => 'automacao',
        'page:servico:desenvolvimento-de-sites-para-empresas' => 'web_mobile',
        'page:servico:criacao-de-landing-pages' => 'web_mobile',
        'page:servico:desenvolvimento-de-aplicativos' => 'web_mobile',
        'page:servico:seo-tecnico-e-otimizacao-de-sites' => 'seo',
        'page:sistemas' => 'produtos',
        'page:sistema:vetoros' => 'produtos',
        'page:sistema:vetorpet' => 'produtos',
        'page:home' => 'institucional',
        'page:sobre' => 'institucional',
        'page:contato' => 'institucional',
        'page:servicos' => 'institucional',
        'page:blog' => 'institucional',
    ];

    /** Temas da estratégia comercial sem página própria: viram pauta de artigo, não página nova. */
    public const TOPICS = [
        'desenvolvimento' => ['Sistemas sob medida', 'Sistemas de gestão empresarial', 'Modernização de sistemas legados'],
        'automacao' => ['Automação de processos empresariais', 'Integração de APIs', 'Integração entre sistemas'],
        'web_mobile' => ['Sites institucionais', 'Landing pages', 'Aplicativos empresariais'],
        'produtos' => ['VetorOS', 'VetorPet', 'VetorLeads'],
        'seo' => ['SEO técnico', 'Performance de sites', 'SEO para sites empresariais'],
    ];

    public function __invoke(PageRegistry $registry): Response
    {
        $pages = $registry->withEffective()->filter(fn ($page) => ! str_starts_with($page['id'], 'privacidade') && ! in_array($page['id'], ['termos', 'cookies'], true));

        $rows = $pages->map(fn (array $page) => [
            'key' => $page['key'],
            'label' => $page['label'],
            'type' => $page['type'],
            'path' => $page['path'],
            'editUrl' => $page['editUrl'],
            'indexable' => $page['indexable'],
            'focusKeyword' => $page['meta']?->focus_keyword,
            'secondaryKeywords' => $page['meta']?->secondary_keywords ?? [],
            'intent' => $page['meta']?->search_intent ?? self::DEFAULT_INTENTS[$page['key']] ?? null,
            'related' => $page['meta']?->related_pages ?? [],
        ])->values();

        $byKey = $rows->keyBy('key');
        $cannibalization = $rows->filter(fn ($row) => filled($row['focusKeyword']))
            ->groupBy(fn ($row) => Str::lower(trim($row['focusKeyword'])))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group, $keyword) => ['keyword' => $keyword, 'pages' => $group->map(fn ($row) => ['label' => $row['label'], 'editUrl' => $row['editUrl']])->values()])
            ->values();

        // Artigos de uma intenção sem relação com a página comercial correspondente.
        $commercial = $rows->filter(fn ($row) => $row['type'] === SeoMeta::TYPE_PAGE && str_contains($row['key'], 'servico:') || str_contains($row['key'], 'sistema:'));
        $missingLinks = $rows->where('type', SeoMeta::TYPE_POST)->filter(fn ($row) => $row['intent'] && $row['intent'] !== 'institucional')
            ->filter(fn ($row) => collect($row['related'])->intersect($commercial->where('intent', $row['intent'])->pluck('key'))->isEmpty())
            ->map(fn ($row) => ['label' => $row['label'], 'editUrl' => $row['editUrl'], 'intent' => $row['intent'], 'suggested' => $commercial->where('intent', $row['intent'])->pluck('label')->values()])
            ->values();

        return Inertia::render('admin/seo/keywords', [
            'rows' => $rows,
            'intents' => SeoMeta::INTENTS,
            'topics' => self::TOPICS,
            'cannibalization' => $cannibalization,
            'missingLinks' => $missingLinks,
            'relatedLabels' => $byKey->map(fn ($row) => $row['label']),
        ]);
    }
}
