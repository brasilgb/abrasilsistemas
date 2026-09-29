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
                ->get(['id', 'name', 'whatsapp_text', 'trial_days', 'trial_expiration_text', 'active']),
        ]);
    }

    public function store(ProspectProductRequest $request): RedirectResponse
    {
        ProspectProduct::query()->create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produto criado.']);

        return to_route('prospect-products.index');
    }

    public function update(ProspectProductRequest $request, ProspectProduct $prospectProduct): RedirectResponse
    {
        $prospectProduct->update($request->validated());
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
