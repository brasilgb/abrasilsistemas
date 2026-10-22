<?php

use App\Models\User;

beforeEach(function () {
    config(['services.vetorchat.widget_key' => str_repeat('a', 48), 'services.vetorchat.script_url' => 'https://chat.example/widget.js']);
});

test('institutional installation uses only the public key and mounts on all public service pages', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();
    expect(substr_count($html, 'data-widget-key='))->toBe(1)
        ->and($html)->toContain('src="https://chat.example/widget.js"')
        ->toContain('data-product="abrasil"')
        ->toContain('data-position="left" data-z-index="50" async');
})->with(['/', '/contato', '/sistemas/vetoros', '/desenvolvimento-de-sites-para-empresas', '/automacao-de-processos', '/politica-de-privacidade']);

test('invalid or missing public configuration leaves the installation inactive', function (?string $key, string $url) {
    config(['services.vetorchat.widget_key' => $key, 'services.vetorchat.script_url' => $url]);
    $this->get('/')->assertOk()->assertDontSee('data-widget-key=', false);
})->with([[null, 'https://chat.example/widget.js'], ['private-token', 'https://chat.example/widget.js'], [str_repeat('a', 48), 'http://chat.example/widget.js']]);

test('administrative and authentication pages never install the visitor widget', function () {
    $this->get('/login')->assertOk()->assertDontSee('data-widget-key=', false);
    $this->actingAs(User::factory()->create())->get('/settings/profile')->assertOk()->assertDontSee('data-widget-key=', false);
});
