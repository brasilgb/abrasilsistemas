<?php

use App\Models\Ebook;
use App\Models\EbookEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access the ebook library', function () {
    $this->get(route('ebooks.library'))->assertRedirect(route('login'));
});

test('users can view ebooks granted to their library', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ebook = Ebook::query()->create([
        'volume_slug' => 'planejamento-e-arquitetura',
        'title' => 'Planejamento e Arquitetura',
        'status' => 'published',
        'published_at' => now(),
    ]);

    EbookEntitlement::query()->create([
        'user_id' => $user->id,
        'ebook_id' => $ebook->id,
        'granted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('ebooks.library'))
        ->assertOk()
        ->assertSee('Planejamento e Arquitetura');
});

test('only entitled users can download an ebook', function () {
    Storage::fake('local');
    Storage::disk('local')->put('ebooks/volume-1.pdf', 'ebook');

    $owner = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $ebook = Ebook::query()->create([
        'volume_slug' => 'planejamento-e-arquitetura',
        'title' => 'Planejamento e Arquitetura',
        'file_path' => 'ebooks/volume-1.pdf',
        'status' => 'published',
    ]);

    EbookEntitlement::query()->create([
        'user_id' => $owner->id,
        'ebook_id' => $ebook->id,
        'granted_at' => now(),
    ]);

    $this->actingAs($otherUser)
        ->get(route('ebooks.download', $ebook))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('ebooks.download', $ebook))
        ->assertDownload('planejamento-e-arquitetura-v1.0.pdf');
});
