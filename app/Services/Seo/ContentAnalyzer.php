<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\SeoMeta;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Análise editorial dos artigos. A pontuação (0–100) é uma avaliação interna de boas
 * práticas: não é a nota do Google nem garante posicionamento. De propósito, não há
 * regra de densidade de palavra-chave nem tamanho mínimo obrigatório de artigo.
 *
 * @phpstan-type Check array{id: string, label: string, status: string, message: string, weight: int}
 */
class ContentAnalyzer
{
    public const GOOD = 'good';

    public const IMPROVE = 'improve';

    public const PROBLEM = 'problem';

    /** Parágrafo longo: acima disso a leitura em tela pequena fica cansativa. */
    private const LONG_PARAGRAPH_WORDS = 150;

    /** Semelhança (Jaccard de trigramas de palavras) a partir da qual o texto é considerado repetido. */
    private const SIMILARITY_THRESHOLD = 0.5;

    private const STOPWORDS = ['a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas', 'um', 'uma', 'para', 'por', 'com', 'que', 'se', 'ao', 'aos'];

    /**
     * @param  array{title?: string|null, meta_title?: string|null, meta_description?: string|null, excerpt?: string|null, slug?: string|null, body?: string|null, focus_keyword?: string|null, post_id?: int|null}  $input
     * @return array{score: int, checks: list<Check>, stats: array<string, int|float|null>, disclaimer: string}
     */
    public function analyze(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $seoTitle = trim((string) ($input['meta_title'] ?? '')) ?: app(SeoSettings::class)->formatTitle($title);
        $description = trim((string) ($input['meta_description'] ?? '')) ?: trim((string) ($input['excerpt'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? '')) ?: Str::slug($title);
        $keyword = Str::lower(trim((string) ($input['focus_keyword'] ?? '')));
        $postId = isset($input['post_id']) ? (int) $input['post_id'] : null;
        $doc = $this->parse((string) ($input['body'] ?? ''));
        $text = $this->text($doc);
        $paragraphs = $this->paragraphs($doc);
        $words = $this->wordCount($text);

        $checks = [
            $this->checkTitle($seoTitle, $postId),
            $this->checkDescription($description, $postId),
            $this->checkHeadings($doc),
            $this->checkIntro($paragraphs),
            $this->checkImages($doc),
            $this->checkInternalLinks($doc),
            $this->checkExternalLinks($doc, $words),
            $this->checkLongParagraphs($paragraphs),
            $this->checkReadability($text),
            $this->checkSlug($slug),
            $this->checkDuplicate($text, $postId),
        ];

        if ($keyword !== '') {
            $checks[] = $this->checkKeyword($keyword, $seoTitle, $description, $slug, $paragraphs[0] ?? '', $postId);
        }

        $total = array_sum(array_column($checks, 'weight'));
        $earned = array_sum(array_map(fn (array $check) => match ($check['status']) {
            self::GOOD => $check['weight'],
            self::IMPROVE => $check['weight'] / 2,
            default => 0,
        }, $checks));

        return [
            'score' => $total > 0 ? (int) round($earned / $total * 100) : 0,
            'checks' => $checks,
            'stats' => [
                'words' => $words,
                'paragraphs' => count($paragraphs),
                'readingMinutes' => max(1, (int) ceil($words / 200)),
                'readability' => $this->readabilityIndex($text),
            ],
            'disclaimer' => 'Avaliação interna de boas práticas editoriais. Não é uma pontuação do Google e não garante posicionamento.',
        ];
    }

    private function parse(string $html): DOMDocument
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        return $doc;
    }

    private function text(DOMDocument $doc): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $doc->getElementById('root')?->textContent) ?? '');
    }

    /** @return list<string> */
    private function paragraphs(DOMDocument $doc): array
    {
        $paragraphs = [];

        foreach ((new DOMXPath($doc))->query('//p|//li') ?: [] as $node) {
            $content = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($content !== '' && $node->nodeName === 'p') {
                $paragraphs[] = $content;
            }
        }

        // Corpo antigo sem <p> (texto puro com quebras de linha).
        if ($paragraphs === []) {
            $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/u', (string) $doc->getElementById('root')?->textContent) ?: [])));
        }

        return $paragraphs;
    }

    private function wordCount(string $text): int
    {
        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    /** @return Check */
    private function check(string $id, string $label, string $status, string $message, int $weight = 10): array
    {
        return compact('id', 'label', 'status', 'message', 'weight');
    }

    /** @return Check */
    private function checkTitle(string $title, ?int $postId): array
    {
        $length = mb_strlen($title);

        if ($length === 0) {
            return $this->check('title', 'Título', self::PROBLEM, 'O artigo está sem título.', 15);
        }

        if ($this->duplicateOf('title', $title, $postId)) {
            return $this->check('title', 'Título', self::PROBLEM, 'Outro artigo usa o mesmo título. Títulos repetidos competem entre si nos resultados.', 15);
        }

        if ($length < 30) {
            return $this->check('title', 'Título', self::IMPROVE, "O título de busca tem {$length} caracteres; descreva melhor o assunto (o Google mostra cerca de 50 a 60).", 15);
        }

        if ($length > 65) {
            return $this->check('title', 'Título', self::IMPROVE, "O título de busca tem {$length} caracteres e deve ser cortado no resultado. Deixe o essencial no início.", 15);
        }

        return $this->check('title', 'Título', self::GOOD, 'Título único e com tamanho adequado.', 15);
    }

    /** @return Check */
    private function checkDescription(string $description, ?int $postId): array
    {
        $length = mb_strlen($description);

        if ($length === 0) {
            return $this->check('description', 'Meta description', self::PROBLEM, 'Sem meta description nem resumo: o buscador vai escolher um trecho qualquer.', 10);
        }

        if ($this->duplicateOf('description', $description, $postId)) {
            return $this->check('description', 'Meta description', self::IMPROVE, 'Outro artigo usa a mesma descrição.', 10);
        }

        if ($length < 70 || $length > 160) {
            return $this->check('description', 'Meta description', self::IMPROVE, "A descrição tem {$length} caracteres. Entre 70 e 160 ela costuma aparecer inteira e explicar o que a pessoa vai encontrar.", 10);
        }

        return $this->check('description', 'Meta description', self::GOOD, 'Descrição com tamanho adequado.', 10);
    }

    /** @return Check */
    private function checkHeadings(DOMDocument $doc): array
    {
        $levels = [];

        foreach ((new DOMXPath($doc))->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $node) {
            $levels[] = (int) substr($node->nodeName, 1);
        }

        if (in_array(1, $levels, true)) {
            return $this->check('headings', 'Hierarquia de títulos', self::PROBLEM, 'O texto tem H1. A página do artigo já usa o título como H1; use H2 e H3 nas seções.');
        }

        if ($levels === []) {
            return $this->check('headings', 'Hierarquia de títulos', self::IMPROVE, 'Sem subtítulos (H2). Dividir o texto em seções ajuda a leitura e o entendimento do assunto.');
        }

        $previous = 1;

        foreach ($levels as $level) {
            if ($level > $previous + 1) {
                return $this->check('headings', 'Hierarquia de títulos', self::IMPROVE, "Há um H{$level} sem o nível anterior (pula de H{$previous} para H{$level}).");
            }

            $previous = $level;
        }

        return $this->check('headings', 'Hierarquia de títulos', self::GOOD, 'Subtítulos em ordem (H2 → H3).');
    }

    /**
     * @param  list<string>  $paragraphs
     * @return Check
     */
    private function checkIntro(array $paragraphs): array
    {
        $first = $paragraphs[0] ?? '';
        $words = $this->wordCount($first);

        if ($words === 0) {
            return $this->check('intro', 'Primeiro parágrafo', self::PROBLEM, 'O texto não tem um parágrafo de introdução.');
        }

        if ($words < 20) {
            return $this->check('intro', 'Primeiro parágrafo', self::IMPROVE, 'A introdução é muito curta. Apresente o problema e o que o leitor vai aprender.');
        }

        return $this->check('intro', 'Primeiro parágrafo', self::GOOD, 'Há um parágrafo introdutório.');
    }

    /** @return Check */
    private function checkImages(DOMDocument $doc): array
    {
        $images = $doc->getElementsByTagName('img');
        $missing = 0;

        foreach ($images as $image) {
            /** @var DOMElement $image */
            if (trim($image->getAttribute('alt')) === '') {
                $missing++;
            }
        }

        if ($images->length === 0) {
            return $this->check('images', 'Texto alternativo das imagens', self::GOOD, 'O texto não tem imagens.', 5);
        }

        return $missing === 0
            ? $this->check('images', 'Texto alternativo das imagens', self::GOOD, 'Todas as imagens têm texto alternativo.', 10)
            : $this->check('images', 'Texto alternativo das imagens', self::PROBLEM, "{$missing} de {$images->length} imagens sem texto alternativo (alt).", 10);
    }

    /** @return array{internal: list<string>, external: list<string>} */
    private function links(DOMDocument $doc): array
    {
        $host = app(SeoSettings::class)->canonicalHost();
        $links = ['internal' => [], 'external' => []];

        foreach ($doc->getElementsByTagName('a') as $anchor) {
            /** @var DOMElement $anchor */
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href)) {
                continue;
            }

            $linkHost = strtolower((string) parse_url($href, PHP_URL_HOST));
            $internal = $linkHost === '' || $linkHost === $host || $linkHost === 'www.'.$host;
            $links[$internal ? 'internal' : 'external'][] = $href;
        }

        return $links;
    }

    /** @return Check */
    private function checkInternalLinks(DOMDocument $doc): array
    {
        $count = count($this->links($doc)['internal']);

        return $count > 0
            ? $this->check('internal_links', 'Links internos', self::GOOD, "{$count} link(s) para outras páginas do site.")
            : $this->check('internal_links', 'Links internos', self::IMPROVE, 'Sem links internos. Aponte para a página de serviço ou produto relacionada e para artigos do mesmo tema.');
    }

    /** @return Check */
    private function checkExternalLinks(DOMDocument $doc, int $words): array
    {
        $count = count($this->links($doc)['external']);

        if ($count > 0) {
            return $this->check('external_links', 'Links externos', self::GOOD, "{$count} link(s) para fontes externas.", 5);
        }

        // Só sugere fonte externa em textos mais longos, onde costuma haver dados citados.
        return $words >= 600
            ? $this->check('external_links', 'Links externos', self::IMPROVE, 'Sem links externos. Se o texto cita dados, normas ou documentação, aponte a fonte.', 5)
            : $this->check('external_links', 'Links externos', self::GOOD, 'Texto curto: link externo só se houver fonte a citar.', 5);
    }

    /**
     * @param  list<string>  $paragraphs
     * @return Check
     */
    private function checkLongParagraphs(array $paragraphs): array
    {
        $long = count(array_filter($paragraphs, fn (string $paragraph) => $this->wordCount($paragraph) > self::LONG_PARAGRAPH_WORDS));

        return $long === 0
            ? $this->check('paragraphs', 'Parágrafos', self::GOOD, 'Parágrafos com tamanho confortável.', 5)
            : $this->check('paragraphs', 'Parágrafos', self::IMPROVE, "{$long} parágrafo(s) com mais de ".self::LONG_PARAGRAPH_WORDS.' palavras. Divida para facilitar a leitura no celular.', 5);
    }

    /**
     * Índice de Flesch adaptado ao português (Martins et al., 1996): 248,835 − 1,015 × palavras
     * por frase − 84,6 × sílabas por palavra. As sílabas são estimadas pelos grupos vocálicos.
     */
    public function readabilityIndex(string $text): ?float
    {
        $sentences = array_filter(preg_split('/[.!?]+(\s|$)/u', $text) ?: [], fn ($sentence) => trim($sentence) !== '');
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $words = array_filter($words, fn (string $word) => preg_match('/\pL/u', $word) === 1);

        if (count($words) < 30 || $sentences === []) {
            return null;
        }

        $syllables = array_sum(array_map(fn ($word) => max(1, preg_match_all('/[aeiouáéíóúâêôãõàü]+/iu', $word)), $words));

        return round(248.835 - 1.015 * (count($words) / count($sentences)) - 84.6 * ($syllables / count($words)), 1);
    }

    /** @return Check */
    private function checkReadability(string $text): array
    {
        $index = $this->readabilityIndex($text);

        return match (true) {
            $index === null => $this->check('readability', 'Legibilidade', self::IMPROVE, 'Texto curto demais para medir a legibilidade.', 5),
            $index >= 50 => $this->check('readability', 'Legibilidade', self::GOOD, "Leitura fácil a razoável (índice {$index}).", 5),
            $index >= 30 => $this->check('readability', 'Legibilidade', self::IMPROVE, "Leitura um pouco difícil (índice {$index}). Frases mais curtas ajudam.", 5),
            default => $this->check('readability', 'Legibilidade', self::PROBLEM, "Leitura difícil (índice {$index}). Encurte as frases e prefira palavras simples.", 5),
        };
    }

    /** @return Check */
    private function checkSlug(string $slug): array
    {
        $parts = array_values(array_filter(explode('-', $slug)));
        $meaningful = array_diff($parts, self::STOPWORDS);

        return match (true) {
            $slug === '' => $this->check('slug', 'URL', self::PROBLEM, 'O artigo está sem URL.', 5),
            ctype_digit(str_replace('-', '', $slug)) || count($meaningful) < 2 => $this->check('slug', 'URL', self::IMPROVE, 'A URL não descreve o assunto. Use duas a cinco palavras do tema.', 5),
            mb_strlen($slug) > 75 || count($parts) > 10 => $this->check('slug', 'URL', self::IMPROVE, 'A URL está longa. Mantenha só as palavras principais.', 5),
            default => $this->check('slug', 'URL', self::GOOD, 'URL curta e descritiva.', 5),
        };
    }

    /** @return Check */
    private function checkDuplicate(string $text, ?int $postId): array
    {
        $shingles = $this->shingles($text);

        if (count($shingles) < 20) {
            return $this->check('duplicate', 'Conteúdo original', self::GOOD, 'Sem texto suficiente para comparar.', 10);
        }

        $worst = null;
        $worstScore = 0.0;

        BlogPost::query()->when($postId, fn ($query) => $query->whereKeyNot($postId))->select(['id', 'title', 'body'])->each(function (BlogPost $post) use ($shingles, &$worst, &$worstScore) {
            $other = $this->shingles(strip_tags((string) $post->body));

            if ($other === []) {
                return;
            }

            $score = count(array_intersect_key($shingles, $other)) / max(1, count($shingles + $other));

            if ($score > $worstScore) {
                $worstScore = $score;
                $worst = $post;
            }
        });

        return $worst !== null && $worstScore >= self::SIMILARITY_THRESHOLD
            ? $this->check('duplicate', 'Conteúdo original', self::PROBLEM, 'O texto é muito parecido com "'.$worst->title.'" ('.round($worstScore * 100).'% em comum). Conteúdo repetido divide a relevância entre as páginas.', 10)
            : $this->check('duplicate', 'Conteúdo original', self::GOOD, 'Nenhum artigo com texto muito semelhante.', 10);
    }

    /** @return array<string, true> */
    private function shingles(string $text): array
    {
        $words = preg_split('/[^\pL\pN]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $shingles = [];

        for ($i = 0; $i + 2 < count($words); $i++) {
            $shingles[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
        }

        return $shingles;
    }

    /**
     * Palavra-chave: só verifica presença nos pontos que importam e se outra página já
     * mira o mesmo termo (canibalização). Não mede densidade.
     *
     * @return Check
     */
    private function checkKeyword(string $keyword, string $title, string $description, string $slug, string $intro, ?int $postId): array
    {
        $missing = [];
        $contains = fn (string $haystack) => str_contains(Str::lower(Str::ascii($haystack)), Str::lower(Str::ascii($keyword)));

        foreach (['título' => $title, 'descrição' => $description, 'primeiro parágrafo' => $intro] as $label => $value) {
            if (! $contains($value)) {
                $missing[] = $label;
            }
        }

        if (! $contains(str_replace('-', ' ', $slug))) {
            $missing[] = 'URL';
        }

        $competing = SeoMeta::query()->whereRaw('LOWER(focus_keyword) = ?', [$keyword])
            ->when($postId, fn ($query) => $query->where(fn ($q) => $q->where('subject_type', '!=', SeoMeta::TYPE_POST)->orWhere('subject_id', '!=', (string) $postId)))
            ->count();

        if ($competing > 0) {
            return $this->check('keyword', 'Palavra-chave principal', self::IMPROVE, "Outra(s) {$competing} página(s) já têm \"{$keyword}\" como palavra-chave principal. Diferencie o foco ou ligue as páginas entre si.");
        }

        return $missing === []
            ? $this->check('keyword', 'Palavra-chave principal', self::GOOD, 'O tema aparece no título, na descrição, na URL e na introdução.')
            : $this->check('keyword', 'Palavra-chave principal', self::IMPROVE, 'O tema não aparece em: '.implode(', ', $missing).'. Use a expressão de forma natural, sem repetir à toa.');
    }

    private function duplicateOf(string $field, string $value, ?int $postId): bool
    {
        $value = Str::lower(trim($value));
        $settings = app(SeoSettings::class);

        if ($field === 'title') {
            return BlogPost::query()->when($postId, fn ($query) => $query->whereKeyNot($postId))->get(['id', 'title'])
                ->contains(fn (BlogPost $post) => Str::lower($settings->formatTitle($post->title)) === $value)
                || SeoMeta::query()->whereRaw('LOWER(meta_title) = ?', [$value])->where(fn ($q) => $q->where('subject_type', '!=', SeoMeta::TYPE_POST)->orWhere('subject_id', '!=', (string) $postId))->exists();
        }

        return BlogPost::query()->when($postId, fn ($query) => $query->whereKeyNot($postId))->whereRaw('LOWER(excerpt) = ?', [$value])->exists()
            || SeoMeta::query()->whereRaw('LOWER(meta_description) = ?', [$value])->where(fn ($q) => $q->where('subject_type', '!=', SeoMeta::TYPE_POST)->orWhere('subject_id', '!=', (string) $postId))->exists();
    }
}
