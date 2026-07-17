<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('authenticated users can view leads', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Assistencia Modelo',
        'status' => 'new',
    ]);

    $response = $this->actingAs($user)->get(route('leads.index'));

    $response->assertOk();
});

test('authenticated users can create leads', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('leads.store'), [
        'company_name' => 'Pet Shop Modelo',
        'product' => 'vetorpet',
        'contact_name' => 'Maria',
        'industry' => 'Pet shop',
        'city' => 'Canoas',
        'state' => 'rs',
        'whatsapp' => '51999999999',
        'status' => 'new',
        'next_follow_up_at' => now()->addDay()->toDateString(),
    ]);

    $response->assertRedirect(route('leads.index', absolute: false));

    $lead = Lead::query()->where('company_name', 'Pet Shop Modelo')->first();

    expect($lead)->not->toBeNull();
    expect($lead->user_id)->toBe($user->id);
    expect($lead->product)->toBe('vetorpet');
    expect($lead->state)->toBe('RS');
});

test('authenticated users can import leads from csv with optional fields', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $csv = implode("\n", [
        'company_name,product,phone,website,industry,city',
        'Pet Shop Scraper,vetorpet,51999999999,https://pet.example,Pet shop,Canoas',
        ',vetoros,51988888888,https://semnome.example,Assistencia,Porto Alegre',
        'Assistencia Modelo,vetoros,5133333333,https://assistencia.example,Assistencia tecnica,Novo Hamburgo',
    ]);

    $response = $this->actingAs($user)->post(route('leads.import'), [
        'csv' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ]);

    $response->assertRedirect(route('leads.index', absolute: false));

    expect(Lead::query()->where('company_name', 'Pet Shop Scraper')->exists())->toBeTrue();
    expect(Lead::query()->where('company_name', 'Assistencia Modelo')->exists())->toBeTrue();
    expect(Lead::query()->where('website', 'https://semnome.example')->exists())->toBeFalse();

    $lead = Lead::query()->where('company_name', 'Pet Shop Scraper')->first();

    expect($lead->whatsapp)->toBeNull();
    expect($lead->phone)->toBe('51999999999');
    expect($lead->website)->toBe('https://pet.example');
    expect($lead->product)->toBe('vetorpet');
    expect($lead->industry)->toBe('Pet shop');
    expect($lead->city)->toBe('Canoas');
    expect($lead->status)->toBe('new');
    expect($lead->source)->toBe('CSV');
});

test('authenticated users can update leads', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $lead = Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Empresa Antiga',
        'status' => 'new',
    ]);

    $response = $this->actingAs($user)->put(route('leads.update', $lead), [
        'company_name' => 'Empresa Editada',
        'product' => 'vetoros',
        'contact_name' => 'Joao',
        'industry' => 'Assistencia tecnica',
        'city' => 'Porto Alegre',
        'state' => 'RS',
        'whatsapp' => '51988888888',
        'status' => 'contacted',
        'notes' => 'Contato inicial realizado.',
    ]);

    $response->assertRedirect(route('leads.index', absolute: false));

    $lead->refresh();

    expect($lead->company_name)->toBe('Empresa Editada');
    expect($lead->status)->toBe('contacted');
    expect($lead->last_contacted_at)->not->toBeNull();
});

test('authenticated users can filter leads', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead A',
        'city' => 'Canoas',
        'state' => 'RS',
        'industry' => 'Pet shop',
        'status' => 'interested',
    ]);

    $response = $this->actingAs($user)->get(route('leads.index', [
        'status' => 'interested',
        'state' => 'RS',
        'industry' => 'Pet',
    ]));

    $response->assertOk();
});

test('authenticated users can filter leads by follow up', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead Atrasado',
        'status' => 'contacted',
        'next_follow_up_at' => now()->subDay()->toDateString(),
    ]);

    Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead Futuro',
        'status' => 'new',
        'next_follow_up_at' => now()->addDay()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('leads.index', [
        'follow_up' => 'overdue',
    ]));

    $response->assertOk();
});

test('authenticated users can register lead activities', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $lead = Lead::query()->create([
        'user_id' => $user->id,
        'company_name' => 'Lead com Historico',
        'status' => 'new',
    ]);
    $contactedAt = now()->subHour()->format('Y-m-d H:i:s');
    $followUpAt = now()->addDays(2)->toDateString();

    $response = $this->actingAs($user)->post(route('leads.activities.store', $lead), [
        'type' => 'whatsapp',
        'status' => 'interested',
        'contacted_at' => $contactedAt,
        'next_follow_up_at' => $followUpAt,
        'description' => 'Cliente pediu apresentacao do sistema.',
    ]);

    $response->assertRedirect();

    $lead->refresh();

    expect(LeadActivity::query()->where('lead_id', $lead->id)->count())->toBe(1);
    expect($lead->status)->toBe('interested');
    expect($lead->last_contacted_at)->not->toBeNull();
    expect($lead->next_follow_up_at->toDateString())->toBe($followUpAt);
});
