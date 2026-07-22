<?php

use App\Models\Lead;

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
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 0);

    $lead = Lead::query()->firstOrFail();

    expect($lead->company_name)->toBe('Assistência Técnica Exemplo')
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

    $this->postJson('/api/prospects/import', $payload)->assertCreated();

    $payload['name'] = 'Nome atualizado';

    $this->postJson('/api/prospects/import', $payload)
        ->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('updated', 1);

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->first()->company_name)->toBe('Nome atualizado');
});
