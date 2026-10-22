<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Metadados de SEO de uma página pública. Campos vazios herdam os valores padrão
 * (da página e das configurações globais); robots_index/robots_follow nulos herdam
 * a diretiva padrão, e um false explícito nunca é sobrescrito.
 *
 * @property int $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property bool|null $robots_index
 * @property bool|null $robots_follow
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image_url
 * @property string|null $og_image_alt
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $focus_keyword
 * @property list<string>|null $secondary_keywords
 * @property string|null $search_intent
 * @property list<string>|null $related_pages
 * @property int|null $editorial_score
 */
class SeoMeta extends Model
{
    public const TYPE_PAGE = 'page';

    public const TYPE_POST = 'post';

    /** Campos editáveis pelo painel (mesma ordem do formulário). */
    public const EDITABLE = [
        'meta_title', 'meta_description', 'canonical_url', 'robots_index', 'robots_follow',
        'og_title', 'og_description', 'og_image_url', 'og_image_alt', 'twitter_title', 'twitter_description',
        'focus_keyword', 'secondary_keywords', 'search_intent', 'related_pages',
    ];

    /** Intenções de busca (ABRASIL-SEO-02 §13): agrupam páginas e artigos para os links internos. */
    public const INTENTS = [
        'desenvolvimento' => 'Desenvolvimento de software',
        'automacao' => 'Automação e integrações',
        'web_mobile' => 'Desenvolvimento web e mobile',
        'seo' => 'SEO e performance',
        'produtos' => 'Produtos',
        'institucional' => 'Institucional',
    ];

    protected $fillable = ['subject_type', 'subject_id', ...self::EDITABLE, 'editorial_score'];

    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'secondary_keywords' => 'array',
            'related_pages' => 'array',
            'editorial_score' => 'integer',
        ];
    }

    public static function for(string $type, string|int $id): ?self
    {
        return static::query()->where('subject_type', $type)->where('subject_id', (string) $id)->first();
    }

    public static function subjectKey(string $type, string|int $id): string
    {
        return $type.':'.$id;
    }
}
