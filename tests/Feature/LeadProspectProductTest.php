<?php

use App\Mail\ContactFormReceived;
use App\Models\Lead;
use App\Models\ProspectProduct;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function leadProductUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function leadProductCatalog(string $name, array $attributes = []): ProspectProduct
{
    return ProspectProduct::query()->create(['name' => $name, ...$attributes]);
}

/** @return array<string, string> */
function leadProductApiToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** @return array<string, mixed> */
function leadProductFormPayload(array $overrides = []): array
{
    return [
        'company_name' => 'Empresa do Catálogo',
        'status' => 'new',
        ...$overrides,
    ];
}

test('a lead belongs to a prospect product and exposes its trial days', function () {
    $product = leadProductCatalog('Energia Solar', ['trial_days' => 21]);
    $lead = Lead::query()->create(['company_name' => 'Solar Ltda', 'status' => 'new', 'prospect_product_id' => $product->id]);

    expect($lead->prospectProduct->is($product))->toBeTrue()
        ->and($lead->prospectProduct->trial_days)->toBe(21)
        ->and($product->leads()->pluck('id')->all())->toBe([$lead->id]);
});

test('prospect product id is nullable and legacy leads keep only the product text', function () {
    $lead = Lead::query()->create(['company_name' => 'Lead Antigo', 'status' => 'new', 'product' => 'site']);

    expect($lead->fresh()->prospect_product_id)->toBeNull()
        ->and($lead->fresh()->product)->toBe('site')
        ->and($lead->prospectProduct)->toBeNull();
});

test('the prospect product id must reference an existing product', function () {
    // Direto no banco (sem o model), para provar a FK.
    expect(fn () => DB::table('leads')->insert(['company_name' => 'Sem FK', 'status' => 'new', 'product' => 'site', 'prospect_product_id' => 999]))
        ->toThrow(QueryException::class);

    $this->actingAs(leadProductUser())
        ->post(route('leads.store'), leadProductFormPayload(['prospect_product_id' => 999]))
        ->assertSessionHasErrors('prospect_product_id');

    expect(Lead::query()->count())->toBe(0);
});

test('deleting a product unlinks its leads without losing the product text', function () {
    $product = leadProductCatalog('VetorPet');
    $lead = Lead::query()->create(['company_name' => 'Pet', 'status' => 'new', 'prospect_product_id' => $product->id]);

    $product->delete();

    expect($lead->fresh()->prospect_product_id)->toBeNull()
        ->and($lead->fresh()->product)->toBe('vetorpet');
});

test('the create form lists only active products ordered by name', function () {
    leadProductCatalog('Zeta Sistemas');
    leadProductCatalog('Alpha Sites');
    leadProductCatalog('Beta Inativo', ['active' => false]);

    $this->actingAs(leadProductUser())
        ->get(route('leads.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/create')
            ->has('prospectProducts', 2)
            ->where('prospectProducts.0.name', 'Alpha Sites')
            ->where('prospectProducts.1.name', 'Zeta Sistemas'));
});

test('a lead is created with an active product and gets the matching legacy text', function (string $name, string $legacy) {
    $product = leadProductCatalog($name);

    $this->actingAs(leadProductUser())
        ->post(route('leads.store'), leadProductFormPayload(['prospect_product_id' => $product->id]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.index', absolute: false));

    $lead = Lead::query()->sole();

    expect($lead->prospect_product_id)->toBe($product->id)
        ->and($lead->product)->toBe($legacy);
})->with([
    'rótulo de Lead::PRODUCTS' => ['VetorPet', 'vetorpet'],
    'rótulo com espaço' => ['Site institucional', 'site'],
    'produto novo' => ['Energia Solar', 'energia_solar'],
]);

test('an inactive product is not available for a new association', function () {
    $inactive = leadProductCatalog('Descontinuado', ['active' => false]);
    $user = leadProductUser();

    $this->actingAs($user)
        ->post(route('leads.store'), leadProductFormPayload(['prospect_product_id' => $inactive->id]))
        ->assertSessionHasErrors('prospect_product_id');

    $active = leadProductCatalog('Ativo');
    $lead = Lead::query()->create(['company_name' => 'Troca', 'status' => 'new', 'prospect_product_id' => $active->id]);

    $this->actingAs($user)
        ->put(route('leads.update', $lead), leadProductFormPayload(['company_name' => 'Troca', 'prospect_product_id' => $inactive->id]))
        ->assertSessionHasErrors('prospect_product_id');

    expect(Lead::query()->count())->toBe(1)
        ->and($lead->fresh()->prospect_product_id)->toBe($active->id);
});

test('a lead can switch to another active product on edit', function () {
    $from = leadProductCatalog('VetorOS');
    $to = leadProductCatalog('VetorPet');
    $lead = Lead::query()->create(['company_name' => 'Troca de produto', 'status' => 'new', 'prospect_product_id' => $from->id]);

    expect($lead->product)->toBe('vetoros');

    $this->actingAs(leadProductUser())
        ->put(route('leads.update', $lead), leadProductFormPayload(['company_name' => 'Troca de produto', 'prospect_product_id' => $to->id]))
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->prospect_product_id)->toBe($to->id)
        ->and($lead->fresh()->product)->toBe('vetorpet');
});

test('a lead keeps a product that was deactivated later', function () {
    $product = leadProductCatalog('Energia Solar');
    leadProductCatalog('Outro Ativo');
    $lead = Lead::query()->create(['company_name' => 'Cliente antigo', 'status' => 'new', 'prospect_product_id' => $product->id]);
    $user = leadProductUser();

    $product->update(['active' => false]);

    $this->actingAs($user)
        ->get(route('leads.edit', $lead))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lead.prospect_product_id', $product->id)
            ->where('lead.prospect_product.name', 'Energia Solar')
            ->has('prospectProducts', 2)
            ->where('prospectProducts.0.name', 'Energia Solar')
            ->where('prospectProducts.0.active', false));

    $this->actingAs($user)
        ->put(route('leads.update', $lead), leadProductFormPayload(['company_name' => 'Cliente antigo', 'status' => 'contacted', 'prospect_product_id' => $product->id]))
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->prospect_product_id)->toBe($product->id)
        ->and($lead->fresh()->status)->toBe('contacted');

    // Outro lead não pode ser associado ao produto inativo.
    $this->actingAs($user)
        ->post(route('leads.store'), leadProductFormPayload(['company_name' => 'Novo', 'prospect_product_id' => $product->id]))
        ->assertSessionHasErrors('prospect_product_id');
});

test('the lead form still accepts the legacy product text and links it when the match is exact', function () {
    $vetorpet = leadProductCatalog('VetorPet');

    $this->actingAs(leadProductUser())
        ->post(route('leads.store'), leadProductFormPayload(['product' => 'vetorpet']))
        ->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();

    expect($lead->product)->toBe('vetorpet')
        ->and($lead->prospect_product_id)->toBe($vetorpet->id);
});

test('a legacy lead without link can be edited keeping its unknown product text', function () {
    leadProductCatalog('VetorOS');
    $lead = Lead::query()->create(['company_name' => 'Syncash antigo', 'status' => 'new', 'product' => 'syncash']);

    // O formulário manda o select vazio ("sem vínculo") e o texto legado no campo oculto.
    $this->actingAs(leadProductUser())
        ->put(route('leads.update', $lead), leadProductFormPayload(['company_name' => 'Syncash antigo', 'status' => 'contacted', 'prospect_product_id' => '', 'product' => 'syncash']))
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->product)->toBe('syncash')
        ->and($lead->fresh()->prospect_product_id)->toBeNull()
        ->and($lead->fresh()->status)->toBe('contacted');

    // Texto desconhecido continua valendo só para o lead que já o tinha.
    $this->actingAs(leadProductUser())
        ->post(route('leads.store'), leadProductFormPayload(['company_name' => 'Novo', 'product' => 'syncash']))
        ->assertSessionHasErrors('product');
});

test('a product needs to be chosen when creating a lead', function () {
    $this->actingAs(leadProductUser())
        ->post(route('leads.store'), leadProductFormPayload())
        ->assertSessionHasErrors(['prospect_product_id', 'product']);
});

test('the import API links the legacy product when a single active product matches', function (string $sent, string $name) {
    $product = leadProductCatalog($name);

    $this->postJson('/api/prospects/import', [
        'name' => 'Empresa API',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/empresa-api',
        'product' => $sent,
    ], leadProductApiToken())->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->product)->toBe(strtolower(trim($sent)))
        ->and($lead->prospect_product_id)->toBe($product->id);
})->with([
    'chave com rótulo' => ['vetoros', 'VetorOS'],
    'rótulo com espaço' => ['site', 'Site institucional'],
    'maiúsculas' => [' VetorPet ', 'vetorpet'],
    'chave fora de Lead::PRODUCTS' => ['syncash', 'SynCash'],
    'nome em formato de chave' => ['energia_solar', 'Energia Solar'],
]);

test('the import API preserves the legacy product without a safe match', function (array $catalog, string $sent) {
    foreach ($catalog as $name => $active) {
        leadProductCatalog($name, ['active' => $active]);
    }

    $this->postJson('/api/prospects/import', [
        'name' => 'Empresa sem vínculo',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/sem-vinculo',
        'product' => $sent,
    ], leadProductApiToken())->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->product)->toBe($sent)
        ->and($lead->prospect_product_id)->toBeNull();
})->with([
    'desconhecido' => [['VetorOS' => true], 'syncash'],
    'só semelhante' => [['VetorOS Plus' => true, 'Sync Cash' => true], 'syncash'],
    'ambíguo' => [['VetorOS' => true, 'vetoros' => true], 'vetoros'],
    'produto inativo' => [['VetorOS' => false], 'vetoros'],
]);

test('the import API without product keeps the legacy default', function () {
    leadProductCatalog('VetorOS');

    $this->postJson('/api/prospects/import', [
        'name' => 'Empresa padrão',
        'hasWebsite' => false,
        'canImprove' => true,
        'mapsUrl' => 'https://www.google.com/maps/place/padrao',
    ], leadProductApiToken())->assertCreated();

    $lead = Lead::query()->sole();

    expect($lead->product)->toBe('vetoros')
        ->and($lead->prospect_product_id)->toBe(ProspectProduct::query()->sole()->id);
});

test('the public contact form and the csv import link the legacy product', function () {
    Queue::fake();
    $site = leadProductCatalog('Site institucional');
    $vetorpet = leadProductCatalog('VetorPet');

    $this->post(route('contato.store'), [
        'contact_name' => 'Maria',
        'company_name' => 'Contato do Site',
        'email' => 'maria@example.test',
        'product' => 'site',
        'notes' => 'Quero um site.',
    ])->assertRedirect();

    $csv = implode("\n", ['company_name,product', 'Pet do CSV,vetorpet']);

    $this->actingAs(leadProductUser())->post(route('leads.import'), [
        'csv' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
    ])->assertRedirect();

    expect(Lead::query()->where('company_name', 'Contato do Site')->sole()->prospect_product_id)->toBe($site->id)
        ->and(Lead::query()->where('company_name', 'Pet do CSV')->sole()->prospect_product_id)->toBe($vetorpet->id);
});

test('status changes and other updates do not touch the product link', function () {
    $product = leadProductCatalog('VetorOS');
    $lead = Lead::query()->create(['company_name' => 'Kanban', 'status' => 'new', 'prospect_product_id' => $product->id]);

    $this->actingAs(leadProductUser())
        ->patch(route('leads.status', $lead), ['status' => 'contacted'])
        ->assertRedirect();

    expect($lead->fresh()->prospect_product_id)->toBe($product->id)
        ->and($lead->fresh()->product)->toBe('vetoros');
});

test('the lead list filters by catalog product and still reaches legacy leads', function () {
    $vetoros = leadProductCatalog('VetorOS');
    $old = leadProductCatalog('Antigo', ['active' => false]);
    $linked = Lead::query()->create(['company_name' => 'Vinculado', 'status' => 'new', 'prospect_product_id' => $vetoros->id]);
    $legacy = Lead::query()->create(['company_name' => 'Legado', 'status' => 'new', 'product' => 'syncash']);
    // Lead gravado antes do catálogo (a migration não vincula nada).
    $unlinked = Lead::query()->create(['company_name' => 'VetorOS sem vínculo', 'status' => 'new', 'product' => 'site']);
    $unlinked->forceFill(['product' => 'vetoros', 'prospect_product_id' => null])->saveQuietly();
    $user = leadProductUser();

    $ids = fn (array $filters) => collect($this->actingAs($user)->get(route('leads.index', $filters))
        ->viewData('page')['props']['leads']['data'])->pluck('id')->sort()->values()->all();

    expect($ids([]))->toBe([$linked->id, $legacy->id, $unlinked->id])
        ->and($ids(['product' => (string) $vetoros->id]))->toBe([$linked->id])
        ->and($ids(['product' => 'syncash']))->toBe([$legacy->id])
        ->and($ids(['product' => 'vetoros']))->toBe([$unlinked->id]);

    $this->actingAs($user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('productOptions', [
                ['value' => (string) $old->id, 'label' => 'Antigo (inativo)'],
                ['value' => (string) $vetoros->id, 'label' => 'VetorOS'],
                ['value' => 'syncash', 'label' => 'syncash (sem vínculo)'],
                ['value' => 'vetoros', 'label' => 'VetorOS (sem vínculo)'],
            ])
            ->where('leads.data', fn ($leads) => collect($leads)->firstWhere('id', $linked->id)['prospect_product']['name'] === 'VetorOS'));
});

test('the dashboard counts leads by catalog product without dropping legacy leads', function () {
    $vetoros = leadProductCatalog('VetorOS');
    leadProductCatalog('Energia Solar');
    $inactive = leadProductCatalog('Antigo');
    Lead::query()->create(['company_name' => 'A', 'status' => 'new', 'prospect_product_id' => $vetoros->id]);
    Lead::query()->create(['company_name' => 'B', 'status' => 'new', 'prospect_product_id' => $vetoros->id]);
    Lead::query()->create(['company_name' => 'C', 'status' => 'new', 'prospect_product_id' => $inactive->id]);
    Lead::query()->create(['company_name' => 'D', 'status' => 'new', 'product' => 'syncash']);
    $inactive->update(['active' => false]);

    $this->actingAs(leadProductUser())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.total', 4)
            ->where('metrics.by_product', [
                ['key' => (string) $inactive->id, 'label' => 'Antigo (inativo)', 'total' => 1],
                ['key' => (string) ProspectProduct::query()->where('name', 'Energia Solar')->value('id'), 'label' => 'Energia Solar', 'total' => 0],
                ['key' => (string) $vetoros->id, 'label' => 'VetorOS', 'total' => 2],
                ['key' => 'syncash', 'label' => 'syncash (sem vínculo)', 'total' => 1],
            ])
            ->where('recentLeads', fn ($leads) => collect($leads)->pluck('prospect_product.name')->filter()->count() === 3));
});

test('guests cannot create or edit leads', function () {
    $product = leadProductCatalog('VetorOS');
    $lead = Lead::query()->create(['company_name' => 'Protegido', 'status' => 'new', 'prospect_product_id' => $product->id]);

    $this->post(route('leads.store'), leadProductFormPayload(['prospect_product_id' => $product->id]))->assertRedirect(route('login'));
    $this->put(route('leads.update', $lead), leadProductFormPayload(['prospect_product_id' => $product->id]))->assertRedirect(route('login'));
    $this->get(route('leads.create'))->assertRedirect(route('login'));

    expect(Lead::query()->count())->toBe(1);
});

test('the link command reports legacy values and links only exact matches', function () {
    $vetoros = leadProductCatalog('VetorOS');
    leadProductCatalog('Site institucional');
    leadProductCatalog('site institucional');
    $old = Lead::query()->create(['company_name' => 'Excluído', 'status' => 'new', 'product' => 'site']);
    $rows = collect([
        ['Antigo 1', 'vetoros'],
        ['Antigo 2', 'vetoros'],
        ['Site', 'site'],
        ['Sync', 'syncash'],
    ])->map(fn ($row) => Lead::query()->create(['company_name' => $row[0], 'status' => 'new', 'product' => 'outro']));
    // Leads gravados antes do catálogo: só o texto legado, sem vínculo.
    foreach ($rows as $index => $lead) {
        $lead->forceFill(['product' => ['vetoros', 'vetoros', 'site', 'syncash'][$index], 'prospect_product_id' => null])->saveQuietly();
    }
    $old->delete();

    $this->artisan('leads:link-prospect-products')
        ->expectsOutputToContain('Nada foi gravado')
        ->assertSuccessful();

    expect(Lead::withTrashed()->whereNotNull('prospect_product_id')->count())->toBe(0);

    $this->artisan('leads:link-prospect-products', ['--apply' => true])
        ->expectsOutputToContain('2 lead(s) vinculado(s).')
        ->assertSuccessful();

    expect(Lead::query()->where('product', 'vetoros')->pluck('prospect_product_id')->all())->toBe([$vetoros->id, $vetoros->id])
        ->and(Lead::withTrashed()->where('product', 'site')->whereNotNull('prospect_product_id')->count())->toBe(0)
        ->and(Lead::query()->where('product', 'syncash')->sole()->prospect_product_id)->toBeNull()
        ->and(Lead::withTrashed()->pluck('product')->sort()->values()->all())->toBe(['site', 'site', 'syncash', 'vetoros', 'vetoros']);
});

test('the contact notification e-mail prefers the catalog product name', function () {
    $product = leadProductCatalog('Energia Solar');
    $lead = Lead::query()->create(['company_name' => 'Solar', 'status' => 'new', 'prospect_product_id' => $product->id]);

    expect((new ContactFormReceived($lead))->content()->with['serviceLabel'])->toBe('Energia Solar');

    $legacy = Lead::query()->create(['company_name' => 'Legado', 'status' => 'new', 'product' => 'site']);

    expect((new ContactFormReceived($legacy))->content()->with['serviceLabel'])->toBe('Site institucional');
});
