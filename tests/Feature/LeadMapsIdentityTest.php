<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Mesmo lugar em duas buscas: muda o trecho @lat,lng,zoom e os parâmetros, não os identificadores.
const LMI_PLACE_URL = 'https://www.google.com/maps/place/Oficina+Exemplo/data=!4m7!3m6!1s0x9519a1b2c3d4e5f6:0x1111111111111111!8m2!3d-29.1678123!4d-51.1794456!16s%2Fg%2F11abc!19sChIJOficinaExemplo0001?authuser=0&hl=pt-BR&rclk=1';
const LMI_PLACE_URL_OTHER_SEARCH = 'https://www.google.com/maps/place/Oficina+Exemplo/@-29.16,-51.17,15z/data=!3m1!4b1!4m6!3m5!1s0x9519a1b2c3d4e5f6:0x1111111111111111!8m2!3d-29.1678123!4d-51.1794456!16s%2Fg%2F11abc!19sChIJOficinaExemplo0001?entry=ttu';
// Só com Place ID (sem FTID): ainda é o mesmo lugar.
const LMI_PLACE_URL_PLACE_ID_ONLY = 'https://www.google.com/maps/place/?q=place_id:ChIJOficinaExemplo0001';
const LMI_OTHER_PLACE_URL = 'https://www.google.com/maps/place/Oficina+Filial/data=!4m7!3m6!1s0x9519a1b2c3d4e5f6:0x2222222222222222!8m2!3d-29.17!4d-51.18!19sChIJOficinaFilial00002?authuser=0';

function lmiHeaders(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** Prospect exatamente como a extensão 1.3.0 envia (campos vazios chegam como ""). */
function lmiProspect(array $overrides = []): array
{
    return [
        'name' => 'Oficina Exemplo',
        'address' => 'R. das Flores, 100',
        'phone' => '(54) 99876-5432',
        'website' => '',
        'hasWebsite' => false,
        'siteStatus' => 'Sem site',
        'canImprove' => true,
        'opportunity' => 'Não foi encontrado site público no perfil. Oportunidade para oferecer criação de site e soluções da AB Sistemas.',
        'mapsUrl' => LMI_PLACE_URL,
        'city' => '',
        'state' => '',
        'category' => 'Oficina mecânica',
        'rating' => 4.7,
        'reviews' => 132,
        'capturedAt' => '2026-09-30T12:00:00Z',
        ...$overrides,
    ];
}

function lmiSend(array $prospects)
{
    return test()->postJson('/api/prospects/import', [
        'source' => 'AB Prospect - Google Maps',
        'product' => 'vetoros',
        'prospects' => $prospects,
    ], lmiHeaders());
}

test('importing stores the Maps place identity extracted from the URL', function () {
    lmiSend([lmiProspect()])->assertCreated()->assertJsonPath('restored', 0);

    $lead = Lead::query()->sole();

    expect($lead->google_place_id)->toBe('ChIJOficinaExemplo0001')
        ->and($lead->maps_feature_id)->toBe('0x9519a1b2c3d4e5f6:0x1111111111111111')
        ->and($lead->latitude)->toBe('-29.1678123')
        ->and($lead->longitude)->toBe('-51.1794456');
});

test('the optional placeId and coordinates of the new extension complete a URL without them', function () {
    lmiSend([lmiProspect([
        'mapsUrl' => 'https://www.google.com/maps/search/oficina/@-29.1,-51.1,14z',
        'placeId' => 'ChIJOficinaExemplo0001',
        'latitude' => -29.1678123,
        'longitude' => -51.1794456,
    ])])->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->google_place_id)->toBe('ChIJOficinaExemplo0001')
        ->and($lead->maps_feature_id)->toBeNull()
        ->and($lead->latitude)->toBe('-29.1678123');
});

test('the Maps URL wins over divergent explicit values and invalid optional values are ignored', function () {
    lmiSend([lmiProspect(['placeId' => 'não é um id!', 'latitude' => -30, 'longitude' => -52])])->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->google_place_id)->toBe('ChIJOficinaExemplo0001')
        ->and($lead->latitude)->toBe('-29.1678123');
});

test('the new optional fields are validated without breaking the old payload', function () {
    lmiSend([lmiProspect(['latitude' => 120])])->assertUnprocessable()->assertJsonValidationErrors('prospects.0.latitude');
    lmiSend([lmiProspect(['websiteChecks' => 'sim'])])->assertUnprocessable()->assertJsonValidationErrors('prospects.0.websiteChecks');

    expect(Lead::query()->count())->toBe(0);
});

test('the same place found by Place ID only is the same lead', function () {
    lmiSend([lmiProspect()])->assertCreated();

    lmiSend([lmiProspect(['mapsUrl' => LMI_PLACE_URL_PLACE_ID_ONLY, 'phone' => ''])])
        ->assertOk()
        ->assertJsonPath('created', 0);

    expect(Lead::query()->count())->toBe(1);
});

test('the Maps place takes precedence over phone and company matches', function () {
    // Cadastrado à mão antes, com o mesmo telefone, mas sem lugar do Maps.
    $byPhone = Lead::query()->create([
        'company_name' => 'Oficina Exemplo',
        'whatsapp' => '5554998765432',
        'status' => 'new',
        'product' => 'vetoros',
    ]);
    $byPlace = Lead::query()->create([
        'company_name' => 'Oficina Exemplo (Maps)',
        'maps_url' => LMI_PLACE_URL_OTHER_SEARCH,
        'status' => 'contacted',
        'product' => 'vetoros',
    ]);

    lmiSend([lmiProspect()])
        ->assertOk()
        ->assertJsonPath('ids.0', $byPlace->id);

    expect($byPhone->fresh()->maps_url)->toBeNull()
        ->and($byPlace->fresh()->status)->toBe('contacted');
});

test('branches sharing a phone stay separate leads', function () {
    lmiSend([lmiProspect()])->assertCreated();

    lmiSend([lmiProspect(['name' => 'Oficina Filial', 'mapsUrl' => LMI_OTHER_PLACE_URL])])
        ->assertCreated()
        ->assertJsonPath('created', 1);

    expect(Lead::query()->count())->toBe(2);
});

test('a soft deleted lead captured again in the same Maps place is restored with its history', function (string $url) {
    lmiSend([lmiProspect()])->assertCreated();
    $lead = Lead::query()->sole();
    $lead->forceFill(['status' => 'contacted', 'notes' => 'Já conversamos.'])->save();
    $lead->activities()->create(['type' => 'whatsapp', 'description' => 'Primeiro contato.']);
    $lead->delete();

    lmiSend([lmiProspect(['mapsUrl' => $url])])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('unchanged', 0)
        ->assertJsonPath('restored', 1)
        ->assertJsonPath('ids.0', $lead->id);

    $lead = Lead::query()->sole();

    expect($lead->trashed())->toBeFalse()
        ->and($lead->status)->toBe('contacted')
        ->and($lead->notes)->toBe('Já conversamos.')
        ->and($lead->activities()->where('type', 'whatsapp')->count())->toBe(1)
        ->and($lead->activities()->where('type', 'note')->sole()->description)
        ->toBe('Lead restaurado pela reimportação (AB Prospect - Google Maps): mesmo lugar do Google Maps.');
})->with([
    'same URL' => LMI_PLACE_URL,
    'Place ID' => LMI_PLACE_URL_PLACE_ID_ONLY,
    'FTID' => 'https://www.google.com/maps/place/Oficina/data=!4m2!3m1!1s0x9519a1b2c3d4e5f6:0x1111111111111111',
]);

test('a soft deleted lead is never restored by phone or by name', function (array $overrides) {
    lmiSend([lmiProspect(['city' => 'Caxias do Sul', 'state' => 'RS'])])->assertCreated();
    $deleted = Lead::query()->sole();
    $deleted->delete();

    lmiSend([lmiProspect([
        'mapsUrl' => '',
        'city' => 'Caxias do Sul',
        'state' => 'RS',
        ...$overrides,
    ])])->assertCreated()->assertJsonPath('restored', 0);

    expect(Lead::onlyTrashed()->sole()->id)->toBe($deleted->id)
        ->and(Lead::query()->count())->toBe(1);
})->with([
    'same phone' => [['name' => 'Outro Nome']],
    'same company, city and state' => [['phone' => '']],
    'same URL without identifiers' => [['mapsUrl' => 'https://www.google.com/maps/search/oficina']],
]);

test('an active lead wins over a deleted one in the same place', function () {
    lmiSend([lmiProspect()])->assertCreated();
    $deleted = Lead::query()->sole();
    $deleted->delete();
    // Criado à mão depois, no mesmo lugar.
    $active = Lead::query()->create(['company_name' => 'Oficina Exemplo', 'maps_url' => LMI_PLACE_URL, 'status' => 'new', 'product' => 'vetoros']);

    lmiSend([lmiProspect()])->assertOk()->assertJsonPath('ids.0', $active->id)->assertJsonPath('restored', 0);

    expect(Lead::onlyTrashed()->sole()->id)->toBe($deleted->id);
});

test('a landline from Maps is not presumed to be WhatsApp', function (string $phone, ?string $whatsapp) {
    lmiSend([lmiProspect(['phone' => $phone])])->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->phone)->toBe($phone)
        ->and($lead->whatsapp)->toBe($whatsapp);
})->with([
    'mobile' => ['(54) 99876-5432', '(54) 99876-5432'],
    'landline' => ['(54) 3222-1111', null],
    '0800' => ['0800 123 4567', null],
]);

test('an explicit WhatsApp is kept even with a landline phone', function () {
    lmiSend([lmiProspect(['phone' => '(54) 3222-1111', 'whatsapp' => '(54) 99111-2222'])])->assertCreated();

    expect(Lead::query()->sole()->whatsapp)->toBe('(54) 99111-2222');
});

test('a landline phone still finds the lead that has it', function () {
    Lead::query()->create(['company_name' => 'Oficina', 'phone' => '5432221111', 'status' => 'new', 'product' => 'vetoros']);

    lmiSend([lmiProspect(['mapsUrl' => '', 'phone' => '(54) 3222-1111'])])->assertOk()->assertJsonPath('created', 0);
});

test('a social profile in the website field is not an own website', function (string $url, string $status, ?string $instagram) {
    // Como a extensão 1.3 envia: marcado como site e sem análise.
    lmiSend([lmiProspect([
        'website' => $url,
        'hasWebsite' => true,
        'siteStatus' => 'Com site - ainda não analisado',
        'canImprove' => false,
        'opportunity' => 'Possui site. Recomenda-se analisar oportunidades de modernização e integração com a AB Sistemas.',
    ])])->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->website)->toBe($url)
        ->and($lead->website_type)->not->toBe('own')
        ->and($lead->has_website)->toBeFalse()
        ->and($lead->can_improve)->toBeTrue()
        ->and($lead->site_status)->toBe($status)
        ->and($lead->opportunity)->toStartWith('O perfil no Maps aponta só para rede social')
        ->and($lead->instagram)->toBe($instagram);
})->with([
    'instagram' => ['https://www.instagram.com/oficinaexemplo/', 'Sem site próprio - só rede social', 'https://www.instagram.com/oficinaexemplo/'],
    'facebook' => ['https://www.facebook.com/oficinaexemplo', 'Sem site próprio - só rede social', null],
    'link-bio' => ['https://linktr.ee/oficinaexemplo', 'Sem site próprio - só link na bio', null],
]);

test('an own website keeps the extension analysis and its checks', function () {
    lmiSend([lmiProspect([
        'website' => 'https://oficinaexemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - pode melhorar',
        'canImprove' => true,
        'websiteChecks' => ['accessible' => true, 'https' => false, 'viewport' => true, 'unknown' => true],
    ])])->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->website_type)->toBe('own')
        ->and($lead->has_website)->toBeTrue()
        ->and($lead->site_status)->toBe('Com site - pode melhorar')
        ->and($lead->website_checks)->toEqual(['accessible' => true, 'https' => false, 'viewport' => true]);
});

test('a later site analysis replaces the "not analyzed yet" state', function () {
    $pending = lmiProspect([
        'website' => 'https://oficinaexemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - ainda não analisado',
        'canImprove' => false,
        'opportunity' => 'Possui site. Recomenda-se analisar oportunidades de modernização e integração com a AB Sistemas.',
    ]);
    lmiSend([$pending])->assertCreated();

    lmiSend([[
        ...$pending,
        'siteStatus' => 'Com site - pode melhorar',
        'canImprove' => true,
        'opportunity' => 'Possui site, mas não utiliza HTTPS.',
        'websiteChecks' => ['accessible' => true, 'https' => false],
    ]])->assertOk()->assertJsonPath('unchanged', 0);

    $lead = Lead::query()->sole();

    expect($lead->site_status)->toBe('Com site - pode melhorar')
        ->and($lead->can_improve)->toBeTrue()
        ->and($lead->opportunity)->toBe('Possui site, mas não utiliza HTTPS.')
        ->and($lead->website_checks)->toEqual(['accessible' => true, 'https' => false])
        ->and($lead->activities()->sole()->description)
        ->toBe('Reimportação (AB Prospect - Google Maps): dados de captação completados — oportunidade, situação do site, pode melhorar, checagens do site.');

    // Uma captura posterior sem análise não desfaz a análise.
    lmiSend([$pending])->assertOk();

    expect($lead->fresh()->site_status)->toBe('Com site - pode melhorar');
});

test('the analysis never overwrites an opportunity written by the commercial team', function () {
    $pending = lmiProspect([
        'website' => 'https://oficinaexemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - ainda não analisado',
    ]);
    lmiSend([$pending])->assertCreated();
    Lead::query()->sole()->update(['opportunity' => 'Cliente quer loja virtual.']);

    lmiSend([[...$pending, 'siteStatus' => 'Com site - básico OK', 'canImprove' => false, 'opportunity' => 'Site ok.']])->assertOk();

    expect(Lead::query()->sole())
        ->site_status->toBe('Com site - básico OK')
        ->opportunity->toBe('Cliente quer loja virtual.');
});

test('a website found later replaces the "no website" state', function () {
    lmiSend([lmiProspect()])->assertCreated();

    lmiSend([lmiProspect([
        'website' => 'https://oficinaexemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - ainda não analisado',
        'canImprove' => false,
    ])])->assertOk();

    expect(Lead::query()->sole())
        ->website->toBe('https://oficinaexemplo.com.br')
        ->has_website->toBeTrue()
        ->site_status->toBe('Com site - ainda não analisado');
});

test('the lead form rejects a lead in the same Maps place', function () {
    Lead::query()->create(['company_name' => 'Oficina Exemplo', 'maps_url' => LMI_PLACE_URL, 'status' => 'new', 'product' => 'vetoros']);

    expect(Lead::findDuplicate(['company_name' => 'Outro nome', 'maps_url' => LMI_PLACE_URL_OTHER_SEARCH]))->not->toBeNull()
        ->and(Lead::findDuplicate(['company_name' => 'Outro nome', 'maps_url' => LMI_OTHER_PLACE_URL]))->toBeNull();
});

test('changing the Maps link on any screen refreshes the place identity', function () {
    $lead = Lead::query()->create(['company_name' => 'Oficina', 'maps_url' => LMI_PLACE_URL, 'status' => 'new', 'product' => 'vetoros']);

    $lead->update(['maps_url' => LMI_OTHER_PLACE_URL]);
    expect($lead->fresh()->google_place_id)->toBe('ChIJOficinaFilial00002');

    $lead->update(['maps_url' => null]);
    expect($lead->fresh())
        ->google_place_id->toBeNull()
        ->maps_feature_id->toBeNull()
        ->latitude->toBeNull();
});

test('the lead exposes phone line and website types', function () {
    $lead = Lead::query()->create([
        'company_name' => 'Oficina',
        'phone' => '(54) 3222-1111',
        'whatsapp' => '(54) 3222-1111',
        'website' => 'https://instagram.com/oficina',
        'status' => 'new',
        'product' => 'vetoros',
    ]);

    expect($lead->toArray())
        ->phone_line_type->toBe('landline')
        ->whatsapp_line_type->toBe('landline')
        ->website_type->toBe('social');
});

/** Leads antigos: link do Maps gravado antes das colunas de identidade existirem. */
function lmiLegacyLeads(): array
{
    Carbon::setTestNow('2026-08-01 10:00:00');
    $active = Lead::query()->create(['company_name' => 'Ativo', 'maps_url' => LMI_PLACE_URL, 'status' => 'new', 'product' => 'vetoros']);
    $deleted = Lead::query()->create(['company_name' => 'Excluído', 'maps_url' => LMI_OTHER_PLACE_URL, 'status' => 'new', 'product' => 'vetoros']);
    $deleted->delete();
    $search = Lead::query()->create(['company_name' => 'Busca', 'maps_url' => 'https://www.google.com/maps/search/oficina', 'status' => 'new', 'product' => 'vetoros']);
    $noUrl = Lead::query()->create(['company_name' => 'Sem link', 'status' => 'new', 'product' => 'vetoros']);
    Carbon::setTestNow();

    DB::table('leads')->update(['google_place_id' => null, 'maps_feature_id' => null, 'latitude' => null, 'longitude' => null]);

    return [$active, $deleted, $search, $noUrl];
}

test('the backfill is a dry run by default', function () {
    Http::preventStrayRequests();
    lmiLegacyLeads();
    $before = DB::table('leads')->orderBy('id')->get()->toArray();

    $this->artisan('leads:backfill-maps-place')
        ->expectsOutputToContain('Simulação (dry-run): nada foi gravado.')
        ->assertSuccessful();

    expect(DB::table('leads')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('the backfill fills the identity of active and deleted leads without calling anything external', function () {
    Http::preventStrayRequests();
    [$active, $deleted, $search, $noUrl] = lmiLegacyLeads();

    $this->artisan('leads:backfill-maps-place --apply')
        ->expectsOutputToContain('2 lead(s) atualizado(s).')
        ->assertSuccessful();

    $row = fn (Lead $lead) => DB::table('leads')->where('id', $lead->id)->first();

    expect($row($active)->google_place_id)->toBe('ChIJOficinaExemplo0001')
        ->and($row($active)->maps_feature_id)->toBe('0x9519a1b2c3d4e5f6:0x1111111111111111')
        ->and((float) $row($active)->latitude)->toBe(-29.1678123)
        ->and($row($deleted)->google_place_id)->toBe('ChIJOficinaFilial00002')
        ->and($row($deleted)->deleted_at)->not->toBeNull()
        ->and($row($search)->google_place_id)->toBeNull()
        ->and($row($noUrl)->google_place_id)->toBeNull()
        // Dado técnico: o backfill não mexe em updated_at.
        ->and(Carbon::parse($row($active)->updated_at)->toDateString())->toBe('2026-08-01')
        ->and(LeadActivity::query()->count())->toBe(0);

    // Idempotente: a segunda execução não tem nada a preencher.
    $this->artisan('leads:backfill-maps-place --apply')
        ->expectsOutputToContain('0 lead(s) atualizado(s).')
        ->assertSuccessful();
});

test('the backfill never overwrites a stored identity', function () {
    [$active] = lmiLegacyLeads();
    DB::table('leads')->where('id', $active->id)->update(['google_place_id' => 'ChIJCorrigidoAMao']);

    $this->artisan('leads:backfill-maps-place --apply')
        ->expectsOutputToContain('google_place_id gravado difere do link do Maps (mantido).')
        ->assertSuccessful();

    $row = DB::table('leads')->where('id', $active->id)->first();

    expect($row->google_place_id)->toBe('ChIJCorrigidoAMao')
        ->and($row->maps_feature_id)->toBe('0x9519a1b2c3d4e5f6:0x1111111111111111');
});

test('after the backfill a recapture restores the deleted lead', function () {
    [, $deleted] = lmiLegacyLeads();
    $this->artisan('leads:backfill-maps-place --apply')->assertSuccessful();

    lmiSend([lmiProspect(['name' => 'Oficina Filial', 'mapsUrl' => LMI_OTHER_PLACE_URL])])
        ->assertOk()
        ->assertJsonPath('restored', 1)
        ->assertJsonPath('ids.0', $deleted->id);
});
