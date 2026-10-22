<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $seo_audit_id
 * @property string $url
 * @property string|null $subject
 * @property int|null $status_code
 * @property string|null $final_url
 * @property string|null $title
 * @property string|null $description
 * @property string|null $canonical
 * @property string|null $robots
 * @property bool $indexable
 * @property bool $in_sitemap
 * @property int|null $h1_count
 * @property int $images_without_alt
 * @property int $internal_links
 * @property int $inbound_links
 * @property int|null $response_ms
 * @property int|null $html_bytes
 */
class SeoAuditPage extends Model
{
    protected $fillable = [
        'seo_audit_id', 'url', 'subject', 'status_code', 'final_url', 'title', 'description', 'canonical', 'robots',
        'indexable', 'in_sitemap', 'h1_count', 'images_without_alt', 'internal_links', 'inbound_links', 'response_ms', 'html_bytes',
    ];

    protected function casts(): array
    {
        return ['indexable' => 'boolean', 'in_sitemap' => 'boolean'];
    }
}
