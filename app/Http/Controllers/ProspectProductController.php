<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProspectProducts\ProspectProductRequest;
use App\Models\ProspectProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProspectProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('prospect-products/index', [
            'products' => ProspectProduct::query()
                ->orderByDesc('active')
                ->orderBy('name')
                ->get()
                // O token da API de cadastro nunca vai para a tela: só se há um salvo (CRM-WA-29).
                ->map(fn (ProspectProduct $product) => [
                    ...$product->only(['id', 'name', 'whatsapp_text', 'trial_days', 'trial_expiration_text', 'active']),
                    ...$product->only(['registration_check_mode', 'registration_api_url', 'registration_lookup_field']),
                    'registration_api_token_set' => filled($product->registration_api_token),
                ]),
            'registrationCheckModes' => ProspectProduct::REGISTRATION_CHECK_MODES,
            'registrationLookupFields' => ProspectProduct::REGISTRATION_LOOKUP_FIELDS,
        ]);
    }

    public function store(ProspectProductRequest $request): RedirectResponse
    {
        ProspectProduct::query()->create($request->productData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto criado.']);

        return to_route('prospect-products.index');
    }

    public function update(ProspectProductRequest $request, ProspectProduct $prospectProduct): RedirectResponse
    {
        $prospectProduct->update($request->productData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto atualizado.']);

        return to_route('prospect-products.index');
    }

    public function active(Request $request, ProspectProduct $prospectProduct): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $prospectProduct->update(['active' => $validated['active']]);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $prospectProduct->active ? 'Produto ativado.' : 'Produto desativado.',
        ]);

        return back();
    }
}
