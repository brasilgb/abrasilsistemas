<?php

use App\Models\ProspectProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function prospectProductAdmin(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function prospectProductPayload(array $overrides = []): array
{
    return [
        'name' => 'Energia Solar',
        'trial_days' => 7,
        'whatsapp_text' => "Olá! Conheça nossa solução de energia solar.\nPodemos conversar?",
        'trial_expiration_text' => 'Seu período de teste terminou. Vamos continuar?',
        'active' => true,
        ...$overrides,
    ];
}

test('admins can list prospect products', function () {
    ProspectProduct::query()->create(['name' => 'Sistema Personalizado', 'active' => false]);
    ProspectProduct::query()->create(['name' => 'Desenvolvimento de Sites', 'trial_days' => 15]);

    $this->actingAs(prospectProductAdmin())
        ->get(route('prospect-products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('prospect-products/index')
            ->has('products', 2)
            ->where('products.0.name', 'Desenvolvimento de Sites')
            ->where('products.0.trial_days', 15)
            ->where('products.0.active', true)
            ->where('products.1.name', 'Sistema Personalizado')
            ->where('products.1.trial_days', null)
            ->where('products.1.active', false));
});

test('admins can create a prospect product and its texts are persisted', function () {
    $payload = prospectProductPayload();

    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), $payload)
        ->assertRedirect(route('prospect-products.index', absolute: false))
        ->assertSessionHasNoErrors();

    $product = ProspectProduct::query()->sole();

    expect($product->name)->toBe('Energia Solar')
        ->and($product->trial_days)->toBe(7)
        ->and($product->whatsapp_text)->toBe($payload['whatsapp_text'])
        ->and($product->trial_expiration_text)->toBe($payload['trial_expiration_text'])
        ->and($product->active)->toBeTrue();
});

test('optional fields may be empty and active defaults to true', function () {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), [
            'name' => 'VetorPet',
            'trial_days' => '',
            'whatsapp_text' => '',
            'trial_expiration_text' => '',
        ])
        ->assertSessionHasNoErrors();

    $product = ProspectProduct::query()->sole();

    expect($product->trial_days)->toBeNull()
        ->and($product->whatsapp_text)->toBeNull()
        ->and($product->trial_expiration_text)->toBeNull()
        ->and($product->active)->toBeTrue();
});

test('trial days of zero are accepted', function () {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), prospectProductPayload(['trial_days' => 0]))
        ->assertSessionHasNoErrors();

    expect(ProspectProduct::query()->sole()->trial_days)->toBe(0);
});

test('trial days have no arbitrary upper limit', function (int $trialDays) {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), prospectProductPayload(['trial_days' => $trialDays]))
        ->assertSessionHasNoErrors();

    expect(ProspectProduct::query()->sole()->trial_days)->toBe($trialDays);
})->with([14, 366, 730, 4294967295]);

test('trial days above the column limit are rejected', function () {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), prospectProductPayload(['trial_days' => 4294967296]))
        ->assertSessionHasErrors('trial_days');

    expect(ProspectProduct::query()->count())->toBe(0);
});

test('trial days can be changed and cleared on edit', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload(['trial_days' => 14]));
    $admin = prospectProductAdmin();

    $this->actingAs($admin)
        ->put(route('prospect-products.update', $product), prospectProductPayload(['trial_days' => 400]))
        ->assertSessionHasNoErrors();

    expect($product->refresh()->trial_days)->toBe(400);

    $this->actingAs($admin)
        ->put(route('prospect-products.update', $product), prospectProductPayload(['trial_days' => null]))
        ->assertSessionHasNoErrors();

    expect($product->refresh()->trial_days)->toBeNull();
});

test('name is required', function (?string $name) {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), prospectProductPayload(['name' => $name]))
        ->assertSessionHasErrors('name');

    expect(ProspectProduct::query()->count())->toBe(0);
})->with([null, '', '   ']);

test('trial days must be a non negative integer', function (mixed $trialDays) {
    $this->actingAs(prospectProductAdmin())
        ->post(route('prospect-products.store'), prospectProductPayload(['trial_days' => $trialDays]))
        ->assertSessionHasErrors('trial_days');

    expect(ProspectProduct::query()->count())->toBe(0);
})->with([-1, 1.5, 'abc']);

test('admins can edit a prospect product', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->actingAs(prospectProductAdmin())
        ->put(route('prospect-products.update', $product), prospectProductPayload([
            'name' => 'Energia Solar Residencial',
            'trial_days' => 30,
            'whatsapp_text' => 'Novo texto',
            'trial_expiration_text' => null,
        ]))
        ->assertRedirect(route('prospect-products.index', absolute: false))
        ->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->name)->toBe('Energia Solar Residencial')
        ->and($product->trial_days)->toBe(30)
        ->and($product->whatsapp_text)->toBe('Novo texto')
        ->and($product->trial_expiration_text)->toBeNull();
});

test('editing validates the same rules', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->actingAs(prospectProductAdmin())
        ->put(route('prospect-products.update', $product), prospectProductPayload(['name' => '', 'trial_days' => -3]))
        ->assertSessionHasErrors(['name', 'trial_days']);

    expect($product->refresh()->name)->toBe('Energia Solar');
});

test('admins can deactivate and reactivate a product without deleting it', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());
    $admin = prospectProductAdmin();

    $this->actingAs($admin)
        ->patch(route('prospect-products.active', $product), ['active' => false])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($product->refresh()->active)->toBeFalse()
        ->and($product->whatsapp_text)->toBe(prospectProductPayload()['whatsapp_text']);

    $this->actingAs($admin)
        ->patch(route('prospect-products.active', $product), ['active' => true])
        ->assertSessionHasNoErrors();

    expect($product->refresh()->active)->toBeTrue()
        ->and(ProspectProduct::query()->count())->toBe(1);
});

test('the edit form can also change the active flag', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->actingAs(prospectProductAdmin())
        ->put(route('prospect-products.update', $product), prospectProductPayload(['active' => false]))
        ->assertSessionHasNoErrors();

    expect($product->refresh()->active)->toBeFalse();
});

test('toggling requires a boolean active flag', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->actingAs(prospectProductAdmin())
        ->patch(route('prospect-products.active', $product), [])
        ->assertSessionHasErrors('active');

    expect($product->refresh()->active)->toBeTrue();
});

test('there is no delete route for prospect products', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->actingAs(prospectProductAdmin())
        ->delete('/prospect-products/'.$product->id)
        ->assertMethodNotAllowed();

    expect(ProspectProduct::query()->count())->toBe(1);
});

test('guests are redirected to login', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());

    $this->get(route('prospect-products.index'))->assertRedirect(route('login'));
    $this->post(route('prospect-products.store'), prospectProductPayload())->assertRedirect(route('login'));
    $this->put(route('prospect-products.update', $product), prospectProductPayload(['name' => 'X']))->assertRedirect(route('login'));
    $this->patch(route('prospect-products.active', $product), ['active' => false])->assertRedirect(route('login'));

    expect(ProspectProduct::query()->sole()->name)->toBe('Energia Solar');
});

test('non admin users are forbidden', function () {
    $product = ProspectProduct::query()->create(prospectProductPayload());
    $reader = User::factory()->reader()->create(['email_verified_at' => now()]);

    $this->actingAs($reader)->get(route('prospect-products.index'))->assertForbidden();
    $this->actingAs($reader)->post(route('prospect-products.store'), prospectProductPayload())->assertForbidden();
    $this->actingAs($reader)->put(route('prospect-products.update', $product), prospectProductPayload(['name' => 'X']))->assertForbidden();
    $this->actingAs($reader)->patch(route('prospect-products.active', $product), ['active' => false])->assertForbidden();

    $product->refresh();

    expect(ProspectProduct::query()->count())->toBe(1)
        ->and($product->name)->toBe('Energia Solar')
        ->and($product->active)->toBeTrue();
});

test('the whatsapp text accepts up to 4000 characters, the manual send limit', function () {
    $admin = prospectProductAdmin();

    $this->actingAs($admin)
        ->post(route('prospect-products.store'), prospectProductPayload(['whatsapp_text' => str_repeat('a', 4000)]))
        ->assertSessionHasNoErrors();

    expect(mb_strlen(ProspectProduct::query()->sole()->whatsapp_text))->toBe(4000);

    $this->actingAs($admin)
        ->post(route('prospect-products.store'), prospectProductPayload(['name' => 'Outro produto', 'whatsapp_text' => str_repeat('a', 4001)]))
        ->assertSessionHasErrors('whatsapp_text');

    $product = ProspectProduct::query()->sole();

    $this->actingAs($admin)
        ->put(route('prospect-products.update', $product), prospectProductPayload(['whatsapp_text' => str_repeat('b', 4001)]))
        ->assertSessionHasErrors('whatsapp_text');

    expect($product->fresh()->whatsapp_text)->toBe(str_repeat('a', 4000));
});
