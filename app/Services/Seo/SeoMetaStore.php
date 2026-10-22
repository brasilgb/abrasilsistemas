<?php

namespace App\Services\Seo;

use App\Models\SeoMeta;
use Illuminate\Validation\Rule;

/**
 * Validação e gravação dos metadados por página (Páginas e metadados e o formulário
 * do artigo). Campos vazios são gravados como null, para herdar o padrão.
 */
class SeoMetaStore
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        $imageUrl = fn ($attribute, $value, $fail) => $value === null || $value === '' || str_starts_with((string) $value, '/') || preg_match('#^https?://#i', (string) $value)
            ? null : $fail('Informe uma URL http(s) ou um caminho do site (/imagem.jpg).');

        return [
            $prefix.'meta_title' => ['nullable', 'string', 'max:255'],
            $prefix.'meta_description' => ['nullable', 'string', 'max:500'],
            $prefix.'canonical_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            $prefix.'robots_index' => ['nullable', 'boolean'],
            $prefix.'robots_follow' => ['nullable', 'boolean'],
            $prefix.'og_title' => ['nullable', 'string', 'max:255'],
            $prefix.'og_description' => ['nullable', 'string', 'max:500'],
            $prefix.'og_image_url' => ['nullable', 'string', 'max:2048', $imageUrl],
            $prefix.'og_image_alt' => ['nullable', 'string', 'max:255'],
            $prefix.'twitter_title' => ['nullable', 'string', 'max:255'],
            $prefix.'twitter_description' => ['nullable', 'string', 'max:500'],
            $prefix.'focus_keyword' => ['nullable', 'string', 'max:120'],
            $prefix.'secondary_keywords' => ['nullable', 'array', 'max:10'],
            $prefix.'secondary_keywords.*' => ['string', 'max:120'],
            $prefix.'search_intent' => ['nullable', Rule::in(array_keys(SeoMeta::INTENTS))],
            $prefix.'related_pages' => ['nullable', 'array', 'max:10'],
            $prefix.'related_pages.*' => ['string', 'max:140', 'regex:/^(page|post):[a-z0-9:_-]+$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $type, string|int $id, array $data, ?int $userId, ?int $score = null): SeoMeta
    {
        $values = [];

        foreach (SeoMeta::EDITABLE as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value)) {
                $value = trim(strip_tags($value));
            }

            if (is_array($value)) {
                $value = array_values(array_unique(array_filter(array_map(fn ($item) => trim(strip_tags((string) $item)), $value)))) ?: null;
            }

            $values[$field] = $value === '' ? null : $value;
        }

        $meta = SeoMeta::query()->firstOrNew(['subject_type' => $type, 'subject_id' => (string) $id]);
        $before = $meta->only(SeoMeta::EDITABLE);
        $meta->fill([...$values, 'editorial_score' => $score ?? $meta->editorial_score])->save();
        $changes = [];

        foreach ($values as $field => $value) {
            if (($before[$field] ?? null) != $value) {
                $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
            }
        }

        if ($changes !== []) {
            SeoChangeLogger::log('seo_meta', SeoMeta::subjectKey($type, $id), 'updated', $changes, $userId);
        }

        return $meta;
    }
}
