<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioItemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/portfolio/index', [
            'items' => PortfolioItem::query()->orderBy('sort_order')->latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/portfolio/form', ['item' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        PortfolioItem::create($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trabalho adicionado.']);

        return to_route('portfolio.index');
    }

    public function edit(PortfolioItem $portfolio): Response
    {
        return Inertia::render('admin/portfolio/form', ['item' => $portfolio]);
    }

    public function update(Request $request, PortfolioItem $portfolio): RedirectResponse
    {
        $portfolio->update($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trabalho atualizado.']);

        return to_route('portfolio.index');
    }

    public function destroy(PortfolioItem $portfolio): RedirectResponse
    {
        $portfolio->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Trabalho excluído.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:500'],
            'screenshot_url' => ['required', 'url', 'max:2048'],
            'site_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['integer', 'min:0'],
            'is_published' => ['boolean'],
        ]);
    }
}
