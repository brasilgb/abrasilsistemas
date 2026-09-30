<?php

namespace App\Services;

/**
 * Tipo do link informado como "site" no perfil do Maps (CRM-LEAD-02): site próprio ou só presença
 * em rede social, link na bio ou WhatsApp. Só pelo domínio, sem acessar o link.
 */
final class WebsiteKind
{
    public const OWN = 'own';

    public const SOCIAL = 'social';

    public const LINK_BIO = 'link_bio';

    public const WHATSAPP = 'whatsapp';

    public const LABELS = [
        self::OWN => 'Site próprio',
        self::SOCIAL => 'Rede social',
        self::LINK_BIO => 'Link na bio',
        self::WHATSAPP => 'Link de WhatsApp',
    ];

    private const DOMAINS = [
        self::SOCIAL => [
            'instagram.com', 'instagr.am', 'facebook.com', 'fb.com', 'fb.me', 'm.me', 'tiktok.com',
            'youtube.com', 'youtu.be', 'twitter.com', 'x.com', 'linkedin.com', 'pinterest.com',
            'threads.net', 'kwai.com',
        ],
        self::LINK_BIO => [
            'linktr.ee', 'linktree.com', 'bio.link', 'bio.site', 'beacons.ai', 'lnk.bio', 'taplink.cc',
            'taplink.ws', 'campsite.bio', 'linkin.bio', 'msha.ke', 'allmylinks.com', 'solo.to', 'hoo.be',
            'linkbio.co', 'bento.me', 'contate.me',
        ],
        self::WHATSAPP => ['wa.me', 'whatsapp.com', 'wa.link'],
    ];

    /** null quando não há link (ou ele não tem domínio). */
    public static function of(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        foreach (self::DOMAINS as $kind => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $kind;
                }
            }
        }

        return self::OWN;
    }

    public static function isInstagram(?string $url): bool
    {
        $host = strtolower((string) parse_url(trim((string) $url), PHP_URL_HOST));

        return in_array($host, ['instagram.com', 'www.instagram.com', 'instagr.am'], true);
    }
}
