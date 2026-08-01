<?php

use App\Models\Lead;

function withProspectToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

test('the public API imports a Google Maps lead', function () {
    $response = $this->postJson('/api/prospects/import', [
        'name' => 'Assistência Técnica Exemplo',
        'address' => 'Rua Exemplo, 100',
        'phone' => '(43) 0000-0000',
        'website' => 'https://exemplo.com.br',
        'hasWebsite' => true,
        'siteStatus' => 'Com site - pode melhorar',
        'canImprove' => true,
        'opportunity' => 'Oportunidade de integração com o VetorOS.',
        'mapsUrl' => 'https://www.google.com/maps/place/exemplo',
        'city' => 'Londrina',
        'state' => 'pr',
        'category' => 'Assistência técnica',
        'rating' => 4.7,
        'reviews' => 132,
        'capturedAt' => '2026-07-22T09:15:22Z',
    ], withProspectToken());

    $response->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    $lead = Lead::query()->firstOrFail();

    expect($lead->company_name)->toBe('Assistência Técnica Exemplo')
        ->and($lead->whatsapp)->toBe('(43) 0000-0000')
        ->and($lead->source)->toBe('AB Prospect - Google Maps')
        ->and($lead->product)->toBe('vetoros')
        ->and($lead->state)->toBe('PR')
        ->and($lead->category)->toBe('Assistência técnica')
        ->and($lead->rating)->toBe('4.70')
        ->and($lead->reviews)->toBe(132)
        ->and($lead->captured_at)->not->toBeNull();
});

test('the public API updates a lead with the same Maps URL', function () {
    $payload = [
        'name' => 'Assistência Técnica Exemplo',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/exemplo',
    ];

    $this->postJson('/api/prospects/import', $payload, withProspectToken())->assertCreated();

    $payload['name'] = 'Nome atualizado';

    $this->postJson('/api/prospects/import', $payload, withProspectToken())
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1);

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->first()->company_name)->toBe('Nome atualizado');
});

test('the public API accepts a custom product destination', function () {
    $response = $this->postJson('/api/prospects/import', [
        'name' => 'Pet Shop Exemplo',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/petshop-exemplo',
        'product' => ' VetorPet ',
    ], withProspectToken());

    $response->assertCreated();

    expect(Lead::query()->firstOrFail()->product)->toBe('vetorpet');
});

test('the API rejects requests without a valid token', function () {
    $payload = [
        'name' => 'Assistência Técnica Exemplo',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/exemplo',
    ];

    $this->postJson('/api/prospects/import', $payload)
        ->assertUnauthorized();

    $this->postJson('/api/prospects/import', $payload, ['Authorization' => 'Bearer wrong-token'])
        ->assertUnauthorized();

    expect(Lead::query()->count())->toBe(0);
});
