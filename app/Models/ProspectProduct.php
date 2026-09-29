<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $whatsapp_text
 * @property int|null $trial_days
 * @property string|null $trial_expiration_text
 * @property bool $active
 */
class ProspectProduct extends Model
{
    protected $fillable = ['name', 'whatsapp_text', 'trial_days', 'trial_expiration_text', 'active'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Produtos disponíveis para uma nova associação (ativos, por nome), mais o produto já
     * vinculado ao lead, mesmo inativo, para a edição não perder o vínculo.
     *
     * @return Collection<int, self>
     */
    public static function options(?int $currentId = null): Collection
    {
        return self::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($currentId, fn ($query, int $id) => $query->orWhere('id', $id)))
            ->orderBy('name')
            ->get(['id', 'name', 'active']);
    }

    /**
     * Texto do envio manual de WhatsApp (CRM-WA-22): o `whatsapp_text` exatamente como foi
     * gravado, ou null quando vazio (o envio usa então o template legado). Vale também para
     * produto inativo: a desativação só impede novos vínculos.
     */
    public function manualWhatsappText(): ?string
    {
        return trim((string) $this->whatsapp_text) === '' ? null : $this->whatsapp_text;
    }

    /**
     * Texto da mensagem automática de teste vencido (CRM-WA-24): o `trial_expiration_text`
     * exatamente como foi gravado, ou null quando vazio ou só com espaços (sem texto, sem envio).
     */
    public function trialExpirationText(): ?string
    {
        return trim((string) $this->trial_expiration_text) === '' ? null : $this->trial_expiration_text;
    }

    /**
     * Textos do envio manual por ID de produto, inclusive inativos, só dos que têm texto.
     *
     * @return array<int, string>
     */
    public static function manualWhatsappTexts(): array
    {
        return self::query()
            ->whereNotNull('whatsapp_text')
            ->get(['id', 'whatsapp_text'])
            ->mapWithKeys(fn (self $product) => [$product->id => $product->manualWhatsappText()])
            ->filter(fn (?string $text) => $text !== null)
            ->all();
    }

    /**
     * Produtos ativos que correspondem exatamente a um valor legado de `leads.product`: o nome,
     * sem diferenciar maiúsculas, igual ao rótulo de Lead::PRODUCTS (vetoros → "VetorOS") ou ao
     * próprio valor (syncash → "Syncash"), ou o nome em formato de chave igual ao valor
     * ("Sistema ERP" → sistema_erp). Nada de semelhança parcial.
     *
     * @return Collection<int, self>
     */
    public static function legacyProductMatches(?string $product): Collection
    {
        $product = mb_strtolower(trim((string) $product));

        if ($product === '') {
            return new Collection;
        }

        $names = array_unique([$product, mb_strtolower(Lead::PRODUCTS[$product] ?? $product)]);

        return self::query()
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (self $candidate) => in_array(mb_strtolower(trim($candidate->name)), $names, true)
                || Str::slug($candidate->name, '_') === $product)
            ->values();
    }

    /** O produto do valor legado, só quando a correspondência é única. */
    public static function resolveLegacyProduct(?string $product): ?self
    {
        $matches = self::legacyProductMatches($product);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Valor gravado em `leads.product` quando o lead é vinculado a este produto: a chave de
     * Lead::PRODUCTS com o mesmo rótulo ("VetorPet" → vetorpet), senão o nome em formato de chave.
     */
    public function legacyProductKey(): string
    {
        $name = mb_strtolower(trim($this->name));

        foreach (Lead::PRODUCTS as $key => $label) {
            if ($name === $key || $name === mb_strtolower($label)) {
                return $key;
            }
        }

        return Str::slug($this->name, '_') ?: 'outro';
    }
}
