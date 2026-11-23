<?php

namespace App\Services;

use App\Rules\Cnpj;
use App\Services\Seo\SeoSettings;

/**
 * Dados institucionais exibidos no site (Painel → SEO → Configurações). Campo vazio não aparece.
 * Localização e área de atendimento são coisas diferentes: o município só é publicado quando a opção
 * "Exibir o município" está ligada, e não existe campo de rua ou número.
 */
class CompanyProfile
{
    public function __construct(private SeoSettings $settings) {}

    /**
     * @return array{name: string, legalName: ?string, cnpj: ?string, location: ?string, serviceArea: list<string>, businessHours: ?string, phone: ?string, summary: string}
     */
    public function toArray(): array
    {
        $profile = [
            'name' => (string) $this->settings->get('company_name'),
            'legalName' => $this->text('organization_legal_name'),
            'cnpj' => $this->cnpj(),
            'location' => $this->location(),
            'serviceArea' => $this->serviceArea(),
            'businessHours' => $this->text('organization_business_hours'),
            'phone' => $this->phone(),
        ];

        return [...$profile, 'summary' => $this->summary($profile)];
    }

    public function cnpj(): ?string
    {
        $cnpj = $this->text('organization_cnpj');

        return $cnpj === null ? null : Cnpj::format($cnpj);
    }

    /** Município e UF, só quando a empresa escolheu publicá-los. */
    public function location(): ?string
    {
        if (! $this->settings->get('organization_show_location')) {
            return null;
        }

        $parts = array_filter([$this->text('organization_city'), $this->text('organization_region')]);

        return $parts === [] ? null : implode('/', $parts);
    }

    /** @return list<string> */
    public function serviceArea(): array
    {
        return array_values(array_filter((array) $this->settings->get('organization_service_area')));
    }

    /** Telefone comercial, quando não é o mesmo número do WhatsApp já exibido. */
    private function phone(): ?string
    {
        $phone = $this->text('organization_phone');
        $digits = fn (?string $value) => preg_replace('/\D+/', '', (string) $value);

        return $phone === null || $digits($phone) === $digits((string) config('contact.whatsapp_number')) ? null : $phone;
    }

    /**
     * Linha institucional do rodapé.
     *
     * @param  array{legalName: ?string, cnpj: ?string, location: ?string, serviceArea: list<string>, businessHours: ?string}  $profile
     */
    private function summary(array $profile): string
    {
        return implode(' · ', array_filter([
            $profile['legalName'],
            $profile['cnpj'] === null ? null : 'CNPJ '.$profile['cnpj'],
            $profile['location'],
            $profile['serviceArea'] === [] ? null : 'Atendimento: '.implode(', ', $profile['serviceArea']),
            $profile['businessHours'] === null ? null : 'Horário: '.$profile['businessHours'],
        ]));
    }

    private function text(string $key): ?string
    {
        return filled($value = $this->settings->get($key)) ? trim((string) $value) : null;
    }
}
