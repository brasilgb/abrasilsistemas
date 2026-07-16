<?php

use App\Models\User;

test('authenticated users can view users management', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('users.index'));

    $response->assertOk();
});

test('authenticated users can create users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('users.store'), [
        'name' => 'Novo Usuario',
        'email' => 'novo@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('users.index', absolute: false));

    $createdUser = User::query()->where('email', 'novo@example.com')->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser->email_verified_at)->not->toBeNull();
});

test('authenticated users can update users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $managedUser = User::factory()->create([
        'email' => 'antigo@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('users.update', $managedUser), [
        'name' => 'Usuario Editado',
        'email' => 'editado@example.com',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertRedirect(route('users.index', absolute: false));

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Usuario Editado');
    expect($managedUser->email)->toBe('editado@example.com');
    expect($managedUser->email_verified_at)->not->toBeNull();
});
