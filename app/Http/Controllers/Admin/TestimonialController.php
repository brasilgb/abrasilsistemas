<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/testimonials/index', [
            'testimonials' => Testimonial::query()->orderBy('sort_order')->latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/testimonials/form', ['testimonial' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Testimonial::create($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento criado.']);

        return to_route('testimonials.index');
    }

    public function edit(Testimonial $testimonial): Response
    {
        return Inertia::render('admin/testimonials/form', ['testimonial' => $testimonial]);
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($this->validated($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento atualizado.']);

        return to_route('testimonials.index');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Depoimento excluído.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'author_name' => ['required', 'string', 'max:120'],
            'author_role' => ['nullable', 'string', 'max:150'],
            'photo_url' => ['nullable', 'url', 'max:2048'],
            'quote' => ['required', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
            'is_published' => ['boolean'],
        ]);
    }
}
