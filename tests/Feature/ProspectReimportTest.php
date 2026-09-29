<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Carbon;

// Mesmo lugar do Google Maps em duas buscas diferentes: muda o trecho @lat,lng,zoom e a query,
// mas o identificador do lugar (!1s0x...:0x...) é o mesmo.
const REIMPORT_MAPS_URL = 'https://www.google.com/maps/place/Pet+Shop+Exemplo/@-23.3100,-51.1600,17z/data=!3m1!4b1!4m6!3m5!1s0x94eb43a1b2c3d4e5:0x1a2b3c4d5e6f7a8b!8m2!3d-23.31!4d-51.16!16s%2Fg%2F11abc?entry=ttu';
const REIMPORT_MAPS_URL_OTHER_SEARCH = 'https://www.google.com/maps/place/Pet+Shop+Exemplo/data=!4m7!3m6!1s0x94eb43a1b2c3d4e5:0x1a2b3c4d5e6f7a8b!8m2!3d-23.31!4d-51.16!16s%2Fg%2F11abc!19sChIJxyz?authuser=0&hl=pt-BR&rclk=1';
const REIMPORT_OTHER_PLACE_URL = 'https://www.google.com/maps/place/Pet+Shop+Filial/data=!4m7!3m6!1s0x94eb43a1b2c3d4e5:0x9999999999999999!8m2!3d-23.32!4d-51.17?authuser=0';

function reimportHeaders(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** Prospect como a extensão envia (campos vazios chegam como ""). */
function reimportProspect(array $overrides = []): array
{
    return [
        'name' => 'Pet Shop Exemplo',
        'address' => 'Rua das Flores, 100 - Centro, Londrina - PR',
        'phone' => '(43) 99876-5432',
        'website' => '',
        'hasWebsite' => false,
        'siteStatus' => 'Sem site',
        'canImprove' => true,
        'opportunity' => 'Sem site: oportunidade de landing page.',
        'mapsUrl' => REIMPORT_MAPS_URL,
        'city' => 'Londrina',
        'state' => 'PR',
        'category' => 'Pet shop',
        'rating' => 4.6,
        'reviews' => 80,
        'capturedAt' => '2026-09-20T12:00:00Z',
        ...$overrides,
    ];
}

function reimportSend(array $prospects, array $payload = [])
{
    return test()->postJson('/api/prospects/import', [
        'source' => 'AB Prospect - Google Maps',
        'product' => 'vetoros',
        ...$payload,
        'prospects' => $prospects,
    ], reimportHeaders());
}

/** Lead já importado e depois trabalhado pelo comercial. */
function reimportWorkedLead(array $attributes = []): Lead
{
    reimportSend([reimportProspect()])->assertCreated();

    $lead = Lead::query()->sole();
    $lead->forceFill($attributes)->save();

    return $lead->refresh();
}

test('importing an unknown prospect creates a new lead', function () {
    reimportSend([reimportProspect()], ['product' => 'vetorpet'])
        ->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0)
        ->assertJsonPath('unchanged', 0);

    $lead = Lead::query()->sole();

    expect($lead->status)->toBe('new')
        ->and($lead->product)->toBe('vetorpet')
        ->and($lead->source)->toBe('AB Prospect - Google Maps')
        ->and($lead->whatsapp)->toBe('(43) 99876-5432')
        ->and($lead->maps_url)->toBe(REIMPORT_MAPS_URL)
        ->and($lead->activities()->count())->toBe(0);
});

test('reimporting an untouched prospect keeps it as the same new lead', function () {
    reimportSend([reimportProspect(['address' => '', 'website' => ''])])->assertCreated();

    reimportSend([reimportProspect(['website' => 'https://petshopexemplo.com.br'])])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('unchanged', 0);

    $lead = Lead::query()->sole();

    expect($lead->status)->toBe('new')
        ->and($lead->address)->toBe('Rua das Flores, 100 - Centro, Londrina - PR')
        ->and($lead->website)->toBe('https://petshopexemplo.com.br');
});

test('reimporting never resets the commercial status', function (string $status) {
    reimportWorkedLead(['status' => $status, 'last_contacted_at' => '2026-09-21 14:00:00']);

    reimportSend([reimportProspect()])->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->status)->toBe($status)
        ->and($lead->last_contacted_at?->format('Y-m-d H:i:s'))->toBe('2026-09-21 14:00:00');
})->with(['contacted', 'interested', 'meeting']);

test('reimporting preserves the owner', function () {
    $owner = User::factory()->create();
    reimportWorkedLead(['user_id' => $owner->id]);

    reimportSend([reimportProspect()])->assertOk();

    expect(Lead::query()->sole()->user_id)->toBe($owner->id);
});

test('reimporting preserves the product chosen by the commercial team', function () {
    reimportWorkedLead(['product' => 'site']);

    reimportSend([reimportProspect()], ['product' => 'vetoros'])->assertOk();

    expect(Lead::query()->sole()->product)->toBe('site');
});

test('reimporting preserves the next follow-up without history notes', function () {
    reimportWorkedLead(['status' => 'contacted', 'next_follow_up_at' => '2026-09-30 09:00']);
    $notesBefore = LeadActivity::query()->count();

    reimportSend([reimportProspect()])->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->next_follow_up_at?->format('Y-m-d H:i'))->toBe('2026-09-30 12:00')
        ->and(LeadActivity::query()->count())->toBe($notesBefore);
});

test('reimporting preserves a converted lead', function () {
    reimportWorkedLead(['status' => 'converted']);

    reimportSend([reimportProspect()])->assertOk();

    expect(Lead::query()->sole()->status)->toBe('converted');
});

test('reimporting preserves a lost lead and its reason', function () {
    reimportWorkedLead(['status' => 'lost', 'lost_reason' => 'price']);

    reimportSend([reimportProspect()])->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->status)->toBe('lost')
        ->and($lead->lost_reason)->toBe('price');
});

test('reimporting exactly the same data changes nothing', function () {
    Carbon::setTestNow('2026-09-20 12:00:00');
    reimportSend([reimportProspect()])->assertCreated();
    $before = Lead::query()->sole()->getAttributes();

    Carbon::setTestNow('2026-09-27 12:00:00');
    reimportSend([reimportProspect()])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('unchanged', 1);

    expect(Lead::query()->sole()->getAttributes())->toBe($before)
        ->and(LeadActivity::query()->count())->toBe(0);
});

test('reimporting only fills capture data the CRM does not have and refreshes Maps metrics', function () {
    reimportSend([reimportProspect([
        'address' => '',
        'category' => '',
        'siteStatus' => '',
        'opportunity' => '',
    ])])->assertCreated();

    Lead::query()->sole()->forceFill([
        'company_name' => 'Pet Shop Exemplo (Matriz)',
        'whatsapp' => '5543991112222',
        'contact_name' => 'Maria',
        'email' => 'maria@petshop.com.br',
        'notes' => 'Prefere contato à tarde.',
        'source' => 'Indicação',
    ])->save();

    reimportSend([reimportProspect([
        'name' => 'PET SHOP EXEMPLO LTDA',
        'phone' => '(43) 3333-4444',
        'website' => 'https://petshopexemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - pode melhorar',
        'canImprove' => true,
        'opportunity' => 'Site lento.',
        'rating' => 4.8,
        'reviews' => 95,
        'capturedAt' => '2026-09-27T10:00:00Z',
    ])], ['source' => 'Outra busca'])->assertOk();

    $lead = Lead::query()->sole();

    // Corrigidos ou criados pelo comercial: preservados.
    expect($lead->company_name)->toBe('Pet Shop Exemplo (Matriz)')
        ->and($lead->whatsapp)->toBe('5543991112222')
        ->and($lead->phone)->toBe('(43) 99876-5432')
        ->and($lead->contact_name)->toBe('Maria')
        ->and($lead->email)->toBe('maria@petshop.com.br')
        ->and($lead->notes)->toBe('Prefere contato à tarde.')
        ->and($lead->source)->toBe('Indicação')
        // Vazios no CRM: completados pela nova captura.
        ->and($lead->address)->toBe('Rua das Flores, 100 - Centro, Londrina - PR')
        ->and($lead->category)->toBe('Pet shop')
        ->and($lead->website)->toBe('https://petshopexemplo.com.br')
        ->and($lead->has_website)->toBeTrue()
        ->and($lead->site_status)->toBe('Com site - pode melhorar')
        ->and($lead->opportunity)->toBe('Site lento.')
        // Métricas do Maps: retrato da última captura.
        ->and($lead->rating)->toBe('4.80')
        ->and($lead->reviews)->toBe(95)
        ->and($lead->captured_at->toIso8601String())->toBe('2026-09-27T10:00:00+00:00');
});

test('reimporting keeps an existing site analysis', function () {
    reimportWorkedLead(['site_status' => 'Com site - pode melhorar', 'has_website' => true, 'website' => 'https://exemplo.com.br']);

    reimportSend([reimportProspect([
        'website' => 'https://exemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - ainda não analisado',
        'canImprove' => false,
        'opportunity' => '',
    ])])->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->site_status)->toBe('Com site - pode melhorar')
        ->and($lead->can_improve)->toBeTrue()
        ->and($lead->opportunity)->toBe('Sem site: oportunidade de landing page.');
});

test('the same Maps place captured from another search does not duplicate the lead', function () {
    reimportWorkedLead(['status' => 'interested']);

    reimportSend([reimportProspect(['mapsUrl' => REIMPORT_MAPS_URL_OTHER_SEARCH])])
        ->assertOk()
        ->assertJsonPath('created', 0);

    $lead = Lead::query()->sole();

    expect($lead->status)->toBe('interested')
        ->and($lead->maps_url)->toBe(REIMPORT_MAPS_URL);
});

test('a prospect without Maps URL matches the existing lead by phone', function () {
    reimportWorkedLead(['status' => 'contacted']);

    reimportSend([reimportProspect(['mapsUrl' => '', 'phone' => '43 9876-5432'])])
        ->assertOk()
        ->assertJsonPath('created', 0);

    expect(Lead::query()->sole()->status)->toBe('contacted');
});

test('a lead registered by hand is found when it is captured from Maps', function () {
    $lead = Lead::query()->create([
        'company_name' => 'Pet Shop Exemplo',
        'whatsapp' => '5543998765432',
        'status' => 'meeting',
        'product' => 'vetorpet',
        'source' => 'Indicação',
    ]);

    reimportSend([reimportProspect()])
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('ids.0', $lead->id);

    $lead->refresh();

    expect(Lead::query()->count())->toBe(1)
        ->and($lead->status)->toBe('meeting')
        ->and($lead->product)->toBe('vetorpet')
        ->and($lead->source)->toBe('Indicação')
        ->and($lead->maps_url)->toBe(REIMPORT_MAPS_URL);
});

test('a prospect matches the existing lead by company, city and state', function () {
    reimportWorkedLead(['status' => 'interested']);

    reimportSend([reimportProspect(['mapsUrl' => '', 'phone' => '', 'name' => 'pet shop exemplo'])])
        ->assertOk()
        ->assertJsonPath('created', 0);

    expect(Lead::query()->sole()->status)->toBe('interested');
});

test('another Maps place sharing the phone becomes a separate lead', function () {
    reimportWorkedLead(['status' => 'interested']);

    reimportSend([reimportProspect(['mapsUrl' => REIMPORT_OTHER_PLACE_URL, 'name' => 'Pet Shop Filial', 'address' => 'Av. Brasil, 9'])])
        ->assertCreated()
        ->assertJsonPath('created', 1);

    expect(Lead::query()->count())->toBe(2)
        ->and(Lead::query()->where('company_name', 'Pet Shop Exemplo')->sole()->status)->toBe('interested');
});

test('the same place twice in one batch creates a single lead', function () {
    reimportSend([
        reimportProspect(),
        reimportProspect(['mapsUrl' => REIMPORT_MAPS_URL_OTHER_SEARCH]),
    ])
        ->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 1);

    expect(Lead::query()->count())->toBe(1);
});

test('reimporting records a history note only when capture data changed', function () {
    reimportSend([reimportProspect(['address' => '', 'website' => ''])])->assertCreated();
    $lead = Lead::query()->sole();

    // Mesmos dados e só métricas do Maps: nenhuma nota.
    reimportSend([reimportProspect(['address' => '', 'website' => ''])])->assertOk();
    reimportSend([reimportProspect(['address' => '', 'website' => '', 'rating' => 4.9, 'reviews' => 120])])->assertOk();

    expect($lead->activities()->count())->toBe(0);

    reimportSend([reimportProspect(['website' => 'https://petshopexemplo.com.br'])])->assertOk();

    $note = $lead->activities()->sole();

    expect($note->type)->toBe('note')
        ->and($note->direction)->toBeNull()
        ->and($note->user_id)->toBeNull()
        ->and($note->description)->toBe('Reimportação (AB Prospect - Google Maps): dados de captação completados — endereço, site.');

    // A nota não é mensagem WhatsApp: não conta como não lida.
    expect($lead->unreadWhatsappMessages()->count())->toBe(0);
});
