<?php

use App\Services\BrazilianPhone;
use App\Services\MapsPlace;
use App\Services\WebsiteKind;

const SIGNALS_PLACE_URL = 'https://www.google.com/maps/place/Oficina+Exemplo/data=!4m7!3m6!1s0x9519a1b2c3d4e5f6:0x1A2B3C4D5E6F7A8B!8m2!3d-29.1678123!4d-51.1794456!16s%2Fg%2F11abc!19sChIJx9Kq_AbC-dEfGhIjKlMnOp?authuser=0&hl=pt-BR&rclk=1';

test('MapsPlace reads Place ID, FTID and the place coordinates from a Maps URL', function () {
    $place = MapsPlace::fromUrl(SIGNALS_PLACE_URL);

    expect($place->placeId)->toBe('ChIJx9Kq_AbC-dEfGhIjKlMnOp')
        ->and($place->featureId)->toBe('0x9519a1b2c3d4e5f6:0x1a2b3c4d5e6f7a8b')
        ->and($place->latitude)->toBe(-29.1678123)
        ->and($place->longitude)->toBe(-51.1794456)
        ->and($place->hasIdentity())->toBeTrue();
});

test('MapsPlace ignores the map viewport and URLs without a place', function (?string $url) {
    $place = MapsPlace::fromUrl($url);

    expect($place->hasIdentity())->toBeFalse()
        ->and($place->latitude)->toBeNull()
        ->and($place->longitude)->toBeNull();
})->with([
    'search URL' => 'https://www.google.com/maps/search/assist%C3%AAncia+t%C3%A9cnica/@-29.16,-51.17,14z',
    'short link' => 'https://maps.app.goo.gl/AbCdEf123',
    'empty' => '',
    'null' => null,
]);

test('MapsPlace reads the ftid and place_id query parameters', function () {
    $place = MapsPlace::fromUrl('https://www.google.com/maps?ftid=0xABC:0xDEF&query_place_id=ChIJabcdefgh');

    expect($place->featureId)->toBe('0xabc:0xdef')
        ->and($place->placeId)->toBe('ChIJabcdefgh');
});

test('MapsPlace discards impossible coordinates', function () {
    expect(MapsPlace::fromUrl('https://www.google.com/maps/place/x/data=!3d0!4d0')->latitude)->toBeNull()
        ->and(MapsPlace::fromValues('ChIJabcdefgh', 95, 10)->latitude)->toBeNull()
        ->and(MapsPlace::fromValues('não é id', -29.1, -51.1)->placeId)->toBeNull()
        ->and(MapsPlace::fromValues('ChIJabcdefgh', '-29.1', '-51.1')->longitude)->toBe(-51.1);
});

test('MapsPlace compares places by identifier', function () {
    $a = new MapsPlace('ChIJaaaaaaaa', '0x1:0x2');

    expect($a->samePlaceAs(new MapsPlace('ChIJaaaaaaaa')))->toBeTrue()
        ->and($a->samePlaceAs(new MapsPlace(featureId: '0x1:0x2')))->toBeTrue()
        ->and($a->samePlaceAs(new MapsPlace('ChIJbbbbbbbb')))->toBeFalse()
        ->and($a->samePlaceAs(new MapsPlace(featureId: '0x1:0x3')))->toBeFalse()
        ->and($a->samePlaceAs(new MapsPlace))->toBeNull()
        // Place ID diferencia maiúsculas.
        ->and($a->samePlaceAs(new MapsPlace('CHIJAAAAAAAA')))->toBeFalse();
});

test('MapsPlace prefers the URL and completes it with explicit values', function () {
    $fromUrl = MapsPlace::fromUrl(SIGNALS_PLACE_URL);
    $explicit = MapsPlace::fromValues('ChIJoutroooo', -30.0, -52.0);

    expect($fromUrl->orElse($explicit))->toEqual($fromUrl)
        ->and(MapsPlace::fromUrl(null)->orElse($explicit))->toEqual($explicit);
});

test('phone line type separates probable mobiles from landlines', function (?string $phone, ?string $type) {
    expect(BrazilianPhone::lineType($phone))->toBe($type);
})->with([
    'mobile with mask' => ['(51) 99876-5432', BrazilianPhone::MOBILE],
    'mobile with 55' => ['+55 51 99876-5432', BrazilianPhone::MOBILE],
    'mobile without ninth digit' => ['(43) 9876-5432', BrazilianPhone::MOBILE],
    'mobile without DDD' => ['99876-5432', BrazilianPhone::MOBILE],
    'landline' => ['(51) 3333-4444', BrazilianPhone::LANDLINE],
    'landline with 55' => ['555133334444', BrazilianPhone::LANDLINE],
    'capital number' => ['4004-1234', BrazilianPhone::LANDLINE],
    '0800' => ['0800 123 4567', BrazilianPhone::LANDLINE],
    'eleven digits without 9' => ['(51) 83333-4444', BrazilianPhone::UNKNOWN],
    'foreign' => ['+1 415 555 0100', BrazilianPhone::UNKNOWN],
    'empty' => ['', null],
    'null' => [null, null],
]);

test('website kind separates own sites from social profiles and link-bio pages', function (?string $url, ?string $kind) {
    expect(WebsiteKind::of($url))->toBe($kind);
})->with([
    ['https://oficinaexemplo.com.br', WebsiteKind::OWN],
    ['oficinaexemplo.com.br/contato', WebsiteKind::OWN],
    ['https://www.instagram.com/oficinaexemplo/', WebsiteKind::SOCIAL],
    ['https://m.facebook.com/oficinaexemplo', WebsiteKind::SOCIAL],
    ['https://www.tiktok.com/@oficina', WebsiteKind::SOCIAL],
    ['https://linktr.ee/oficinaexemplo', WebsiteKind::LINK_BIO],
    ['https://oficina.bio.link', WebsiteKind::LINK_BIO],
    ['https://wa.me/5551999999999', WebsiteKind::WHATSAPP],
    ['https://api.whatsapp.com/send?phone=55', WebsiteKind::WHATSAPP],
    // Domínio que só termina parecido não é rede social.
    ['https://meuinstagram.com.br', WebsiteKind::OWN],
    ['', null],
    [null, null],
]);
