<?php

use App\Models\Lead;
use App\Models\ProspectProduct;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const PRODUCT_TEXT_N8N_URL = 'https://n8n.test/webhook/crm-wa-22';

function productTextUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function productTextCatalog(string $name, array $attributes = []): ProspectProduct
{
    return ProspectProduct::query()->create(['name' => $name, ...$attributes]);
}

function productTextLead(?ProspectProduct $product, array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Empresa CRM-WA-22',
        'status' => 'new',
        'whatsapp' => '51999998888',
        ...($product ? ['prospect_product_id' => $product->id] : ['product' => 'vetoros']),
        ...$attributes,
    ]);
}

function productTextOnEdit(Lead $lead, ?string $expected, string $query = ''): void
{
    test()->actingAs(productTextUser())
        ->get(route('leads.edit', $lead).$query)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/edit')
            ->where('productWhatsappText', $expected));
}

test('the manual WhatsApp text is the product text exactly as stored, or null when empty', function () {
    $multiline = "  Olá! Conheça o VetorOS.\n\nTeste grátis:\r\nvetoros.com.br  ";

    expect(productTextCatalog('VetorOS', ['whatsapp_text' => $multiline])->manualWhatsappText())->toBe($multiline)
        ->and(productTextCatalog('Sem texto')->manualWhatsappText())->toBeNull()
        ->and(productTextCatalog('Vazio', ['whatsapp_text' => ''])->manualWhatsappText())->toBeNull()
        ->and(productTextCatalog('Só espaços', ['whatsapp_text' => " \n\t "])->manualWhatsappText())->toBeNull();
});

test('a lead linked to VetorOS with WhatsApp text uses the product text', function () {
    $product = productTextCatalog('VetorOS', ['whatsapp_text' => 'Olá! Conheça nosso sistema VetorOS para gestão de assistências técnicas.']);

    productTextOnEdit(productTextLead($product), 'Olá! Conheça nosso sistema VetorOS para gestão de assistências técnicas.');
});

test('a lead linked to VetorPet with WhatsApp text uses the product text', function () {
    $product = productTextCatalog('VetorPet', ['whatsapp_text' => 'Olá! Conheça o VetorPet.']);
    $lead = productTextLead($product);

    expect($lead->product)->toBe('vetorpet');
    productTextOnEdit($lead, 'Olá! Conheça o VetorPet.');
});

test('a new dynamic product uses its own text instead of the VetorOS template', function () {
    $product = productTextCatalog('Energia Solar', ['whatsapp_text' => 'Olá! Trabalhamos com soluções de energia solar...']);
    $lead = productTextLead($product);

    expect($lead->product)->toBe('energia_solar');
    productTextOnEdit($lead, 'Olá! Trabalhamos com soluções de energia solar...');
});

test('a linked product with null WhatsApp text falls back to the legacy template', function () {
    $lead = productTextLead(productTextCatalog('VetorPet'));

    // null → a tela usa o template legado escolhido por lead.product (vetorpet).
    expect($lead->product)->toBe('vetorpet');
    productTextOnEdit($lead, null);
});

test('a linked product with empty WhatsApp text falls back to the legacy template', function () {
    $product = productTextCatalog('VetorOS');
    DB::table('prospect_products')->where('id', $product->id)->update(['whatsapp_text' => '']);

    productTextOnEdit(productTextLead($product), null);
});

test('a legacy lead without a product link keeps the legacy behaviour', function () {
    $lead = productTextLead(null, ['product' => 'vetorpet']);

    expect($lead->prospect_product_id)->toBeNull();
    productTextOnEdit($lead, null);
});

test('an inactive product already linked keeps its WhatsApp text for the lead', function () {
    $product = productTextCatalog('Syncash', ['whatsapp_text' => 'Olá! Conheça o Syncash.']);
    $lead = productTextLead($product);
    $product->update(['active' => false]);

    productTextOnEdit($lead, 'Olá! Conheça o Syncash.');
});

test('the text keeps line breaks and placeholders untouched', function () {
    $text = "Olá, {nome}!\n\nSomos da {empresa}.\r\n\r\nAbraço";
    $lead = productTextLead(productTextCatalog('Produto Multilinha', ['whatsapp_text' => $text]), ['contact_name' => 'Maria']);

    productTextOnEdit($lead, $text);
});

test('the text comes from the lead link, never from a product id sent by the client', function () {
    $own = productTextCatalog('Produto Próprio', ['whatsapp_text' => 'Texto próprio']);
    $other = productTextCatalog('Outro Produto', ['whatsapp_text' => 'Texto de outro produto']);

    productTextOnEdit(productTextLead($own), 'Texto próprio', "?prospect_product_id={$other->id}");
    productTextOnEdit(productTextLead(null), null, "?prospect_product_id={$other->id}");
});

test('the leads index sends each product text once, including inactive products', function () {
    $vetoros = productTextCatalog('VetorOS', ['whatsapp_text' => "Olá!\nVetorOS"]);
    $inactive = productTextCatalog('Antigo', ['whatsapp_text' => 'Texto antigo', 'active' => false]);
    $empty = productTextCatalog('Vazio');
    DB::table('prospect_products')->insert(['name' => 'Em branco', 'whatsapp_text' => '   ', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    productTextLead($vetoros);
    productTextLead($empty);

    $this->actingAs(productTextUser())
        ->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('productWhatsappTexts', [$vetoros->id => "Olá!\nVetorOS", $inactive->id => 'Texto antigo'])
            // O texto não se repete em cada lead: a tela escolhe pelo prospect_product_id.
            ->where('leads.data', fn ($leads) => collect($leads)->pluck('prospect_product_id')->sort()->values()->all() === [$vetoros->id, $empty->id]
                && collect($leads)->every(fn ($lead) => ! array_key_exists('whatsapp_text', $lead['prospect_product']))));
});

test('sending the product text through the CRM keeps the n8n contract and the text unchanged', function () {
    config([
        'services.n8n.whatsapp_webhook_url' => PRODUCT_TEXT_N8N_URL,
        'services.n8n.whatsapp_webhook_header' => 'X-CRM-Token',
        'services.n8n.whatsapp_webhook_token' => 'token-crm-wa-22',
    ]);
    Http::preventStrayRequests();
    Http::fake([PRODUCT_TEXT_N8N_URL => Http::response([
        'success' => true,
        'message_id' => '3EB0CRMWA22',
        'status' => 'PENDING',
        'activity_id' => 1,
    ])]);
    $text = "Olá! Conheça o VetorOS.\n\nTeste grátis: vetoros.com.br";
    $lead = productTextLead(productTextCatalog('VetorOS', ['whatsapp_text' => $text]));

    $this->actingAs(productTextUser())
        ->from(route('leads.edit', $lead))
        ->post(route('leads.whatsapp.store', $lead), ['message' => $text])
        ->assertRedirect(route('leads.edit', $lead))
        ->assertSessionHasNoErrors();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === PRODUCT_TEXT_N8N_URL
        && $request->data() === [
            'prospect_id' => $lead->id,
            'nome' => 'Empresa CRM-WA-22',
            'whatsapp' => '555199998888',
            'mensagem' => $text,
        ]);
});
