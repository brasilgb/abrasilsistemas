<?php

use App\Models\User;
use App\Rules\Cnpj;
use App\Services\Seo\SeoSettings;
use App\Services\Seo\StructuredData;
use Inertia\Testing\AssertableInertia as Assert;

// ABRASIL-COMERCIAL-02 §5: dados institucionais e regionais.

function saveCompany(array $values): void
{
    app(SeoSettings::class)->save($values);
    app()->forgetScopedInstances();
}

test('empty institutional data is never presented', function () {
    $organization = app(StructuredData::class)->organization();

    expect($organization)->not->toHaveKeys(['legalName', 'taxID', 'address'])->and($organization['areaServed'])->toBe('BR');
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('company.legalName', null)->where('company.cnpj', null)->where('company.location', null)
        ->where('company.summary', 'Atendimento: Brasil'));
});

test('the city is published only when the company chooses to and never replaces the service area', function () {
    saveCompany(['organization_city' => 'Porto Alegre', 'organization_region' => 'RS', 'organization_service_area' => ['Rio Grande do Sul', 'Brasil']]);
    expect(app(StructuredData::class)->organization())->not->toHaveKey('address')
        ->and(app(StructuredData::class)->organization()['areaServed'])->toBe(['Rio Grande do Sul', 'BR']);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('company.location', null))->assertDontSee('Porto Alegre');

    saveCompany(['organization_show_location' => true]);
    expect(app(StructuredData::class)->organization()['address'])->toBe(['@type' => 'PostalAddress', 'addressLocality' => 'Porto Alegre', 'addressRegion' => 'RS', 'addressCountry' => 'BR']);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('company.location', 'Porto Alegre/RS'));
});

test('the panel saves the institutional data and validates CNPJ and state', function () {
    $admin = User::factory()->create();
    $payload = [...app(SeoSettings::class)->all(), 'organization_cnpj' => '11.222.333/0001-81', 'organization_region' => 'RS', 'organization_business_hours' => 'Segunda a sexta, das 9h às 18h', 'organization_service_area' => [' Brasil ', '<b>RS</b>', 'Brasil']];

    $this->actingAs($admin)->put('/admin/seo/configuracoes', $payload)->assertSessionHasNoErrors();
    app()->forgetScopedInstances();
    expect(app(SeoSettings::class)->get('organization_cnpj'))->toBe('11222333000181')
        ->and(app(SeoSettings::class)->get('organization_service_area'))->toBe(['Brasil', 'RS'])
        ->and(app(StructuredData::class)->organization()['taxID'])->toBe('11.222.333/0001-81');

    $this->actingAs($admin)->put('/admin/seo/configuracoes', [...$payload, 'organization_cnpj' => '11.222.333/0001-82', 'organization_region' => 'XX'])
        ->assertSessionHasErrors(['organization_cnpj', 'organization_region']);
});

test('CNPJ accepts the numeric and the alphanumeric formats', function (string $cnpj, bool $valid) {
    expect(Cnpj::isValid($cnpj))->toBe($valid);
})->with([['11222333000181', true], ['12.ABC.345/01DE-35', true], ['12ABC34501DE36', false], ['00000000000000', false], ['123', false]]);
