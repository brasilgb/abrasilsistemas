<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const PROSPECT_API_TOKEN = 'prospect_api_token';

    // WhatsApp da empresa (remetente); ver App\Services\CompanyWhatsappSettings.
    public const COMPANY_WHATSAPP_ENABLED = 'company_whatsapp_enabled';

    public const COMPANY_WHATSAPP_NUMBER = 'company_whatsapp_number';

    public const COMPANY_WHATSAPP_PROVIDER = 'company_whatsapp_provider';

    public const COMPANY_WHATSAPP_SESSION = 'company_whatsapp_session';

    // SEO: configurações globais (JSON) e token OAuth do Search Console; ver App\Services\Seo.
    public const SEO_SETTINGS = 'seo_settings';

    public const SEO_GSC_TOKEN = 'seo_gsc_token';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }
}
