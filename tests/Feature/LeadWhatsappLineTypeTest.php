<?php

use App\Models\Lead;
use App\Models\ProspectProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsappBusinessHours;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * CRM-LEAD-02.2: o tipo da linha (mobile/landline/unknown) é só estrutural. Fixo não é "sem
 * WhatsApp": fica como WhatsApp não verificado, e um WhatsApp informado vale mesmo sendo fixo.
 * Site também não interfere na aceitação do lead.
 */

const LWL_MAPS_URL = 'https://www.google.com/maps/place/Oficina+Exemplo/data=!4m7!3m6!1s0x9519a1b2c3d4e5f6:0x1111111111111111!8m2!3d-29.1678123!4d-51.1794456!19sChIJOficinaExemplo0001';
const LWL_N8N_URL = 'https://n8n.test/webhook/crm-enviar-whatsapp';

/** Prospect como a extensão 1.4.0 envia, com os campos vazios chegando como "". */
function lwlProspect(array $overrides = []): array
{
    return [
        'name' => 'Oficina Exemplo',
        'address' => 'R. das Flores, 100',
        'phone' => '+55 54 99876-5432',
        'website' => '',
        'hasWebsite' => false,
        'siteStatus' => 'Sem site',
        'canImprove' => true,
        'opportunity' => 'Não foi encontrado site público no perfil.',
        'mapsUrl' => LWL_MAPS_URL,
        'placeId' => 'ChIJOficinaExemplo0001',
        'city' => '',
        'state' => '',
        'category' => 'Oficina mecânica',
        'rating' => 4.7,
        'reviews' => 132,
        'capturedAt' => '2026-09-30T12:00:00Z',
        ...$overrides,
    ];
}

function lwlImport(array $prospects)
{
    return test()->postJson('/api/prospects/import', [
        'source' => 'AB Prospect - Google Maps',
        'product' => 'vetoros',
        'prospects' => $prospects,
    ], ['Authorization' => 'Bearer '.config('services.ab_prospect.token')]);
}

function lwlLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Oficina Exemplo',
        'product' => 'vetoros',
        'status' => 'new',
        ...$attributes,
    ]);
}

test('the line type stays structural and the WhatsApp status never means no WhatsApp', function (string $phone, string $lineType, ?string $whatsapp, string $status) {
    lwlImport([lwlProspect(['phone' => $phone])])->assertCreated();

    expect(Lead::query()->sole()->toArray())
        ->phone->toBe($phone)
        ->phone_line_type->toBe($lineType)
        ->whatsapp->toBe($whatsapp)
        ->whatsapp_status->toBe($status);
})->with([
    // Celular: continua sendo copiado como candidato provável, como antes.
    'celular' => ['+55 54 99876-5432', 'mobile', '+55 54 99876-5432', 'probable'],
    // Fixo e formato desconhecido: não copiados, mas "não verificado", não "sem WhatsApp".
    'fixo' => ['+55 54 3222-1111', 'landline', null, 'unverified'],
    '0800' => ['0800 123 4567', 'landline', null, 'unverified'],
    'desconhecido' => ['+55 54 1234-5678', 'unknown', null, 'unverified'],
]);

test('a landline explicitly used as WhatsApp is kept as informed', function () {
    lwlImport([lwlProspect(['phone' => '+55 54 3222-1111', 'whatsapp' => '+55 54 3222-1111'])])->assertCreated();

    expect(Lead::query()->sole()->toArray())
        ->whatsapp->toBe('+55 54 3222-1111')
        ->phone_line_type->toBe('landline')
        ->whatsapp_line_type->toBe('landline')
        ->whatsapp_status->toBe('informed');
});

test('reimporting a landline does not erase the WhatsApp already informed', function (?string $whatsapp) {
    $lead = lwlLead(['phone' => '5432221111', 'whatsapp' => '5432221111', 'google_place_id' => 'ChIJOficinaExemplo0001']);

    lwlImport([lwlProspect(['phone' => '+55 54 3222-1111', 'whatsapp' => $whatsapp])])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('ids.0', $lead->id);

    expect($lead->refresh())
        ->whatsapp->toBe('5432221111')
        ->phone_line_type->toBe('landline')
        ->whatsapp_status->toBe('informed');
})->with([
    'sem whatsapp na captura' => [''],
    'whatsapp ausente' => [null],
]);

test('the WhatsApp status separates probable, informed and unverified', function (array $attributes, ?string $status) {
    expect(lwlLead($attributes)->whatsapp_status)->toBe($status);
})->with([
    'sem número' => [[], null],
    'celular sem campo WhatsApp' => [['phone' => '(54) 99876-5432'], 'probable'],
    'celular copiado para o WhatsApp' => [['phone' => '(54) 99876-5432', 'whatsapp' => '54998765432'], 'probable'],
    'celular diferente informado' => [['phone' => '(54) 99876-5432', 'whatsapp' => '54991112222'], 'informed'],
    'fixo sem campo WhatsApp' => [['phone' => '(54) 3222-1111'], 'unverified'],
    'fixo informado como WhatsApp' => [['phone' => '(54) 3222-1111', 'whatsapp' => '5432221111'], 'informed'],
    'só o WhatsApp, fixo' => [['whatsapp' => '5432221111'], 'informed'],
    'formato desconhecido' => [['phone' => '1234'], 'unverified'],
]);

test('manual sending is not blocked because the WhatsApp is a landline', function () {
    config([
        'services.n8n.whatsapp_webhook_url' => LWL_N8N_URL,
        'services.n8n.whatsapp_webhook_header' => 'X-CRM-Token',
        'services.n8n.whatsapp_webhook_token' => 'token',
    ]);
    Http::preventStrayRequests();
    Http::fake([LWL_N8N_URL => Http::response(['success' => true, 'message_id' => 'ABC', 'status' => 'PENDING', 'activity_id' => 1])]);
    $lead = lwlLead(['phone' => '(54) 3222-1111', 'whatsapp' => '5432221111']);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.whatsapp.store', $lead), ['message' => 'Olá!'])
        ->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request) => $request['whatsapp'] === '555432221111');
});

test('the trial expired automation does not skip a landline WhatsApp', function () {
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));
    Setting::query()->create(['key' => Setting::TRIAL_EXPIRED_AUTOMATION_SINCE, 'value' => '2026-01-01T00:00:00Z']);
    app(WhatsappBusinessHours::class)->save([
        'timezone' => Lead::FOLLOW_UP_TIMEZONE,
        'days' => array_fill_keys(range(1, 7), ['active' => true, 'opens_at' => '00:00', 'closes_at' => '24:00']),
    ]);
    $product = ProspectProduct::query()->create(['name' => 'VetorOS', 'trial_days' => 7, 'trial_expiration_text' => 'Seu teste terminou.']);
    $lead = lwlLead([
        'status' => 'meeting',
        'phone' => '(54) 3222-1111',
        'whatsapp' => '5432221111',
        'prospect_product_id' => $product->id,
        'trial_started_at' => '2026-09-10',
        'trial_ends_at' => '2026-09-20',
    ]);

    expect(Lead::trialExpiredAutomationCandidates()->modelKeys())->toBe([$lead->id]);
});

test('a prospect is imported normally whatever its website is', function (string $website, bool $hasWebsite, string $type) {
    lwlImport([lwlProspect([
        'phone' => '+55 54 3222-1111',
        'website' => $website,
        'hasWebsite' => $hasWebsite,
    ])])->assertCreated()->assertJsonPath('created', 1);

    $lead = Lead::query()->sole();

    expect($lead->company_name)->toBe('Oficina Exemplo')
        ->and($lead->google_place_id)->toBe('ChIJOficinaExemplo0001')
        ->and($lead->phone)->toBe('+55 54 3222-1111')
        ->and($lead->website_type)->toBe($type === 'none' ? null : $type)
        ->and($lead->has_website)->toBe($type === 'own');
})->with([
    'sem site (vazio)' => ['', false, 'none'],
    'só Instagram' => ['https://www.instagram.com/oficinaexemplo', true, 'social'],
    'só Facebook' => ['https://www.facebook.com/oficinaexemplo', true, 'social'],
    'só link-bio' => ['https://linktr.ee/oficinaexemplo', true, 'link_bio'],
    'só wa.me' => ['https://wa.me/555432221111', true, 'whatsapp'],
    'site próprio' => ['https://oficinaexemplo.com.br', true, 'own'],
]);

test('a prospect with website null and only a phone is imported', function () {
    lwlImport([lwlProspect(['website' => null, 'phone' => '+55 54 3222-1111'])])->assertCreated();

    expect(Lead::query()->sole())
        ->website->toBeNull()
        ->has_website->toBeFalse();
});
