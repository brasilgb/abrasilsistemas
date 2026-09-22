<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class LeadSettingsController extends Controller
{
    /**
     * Show the lead capture settings page.
     */
    public function edit(): Response
    {
        $extension = collect(File::exists(public_path('files')) ? File::files(public_path('files')) : [])
            ->filter(fn ($file) => in_array($file->getExtension(), ['xpi', 'zip'], true)
                && str_starts_with($file->getFilename(), 'ab-prospect-firefox-'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->first();

        return Inertia::render('settings/leads', [
            'prospectApiToken' => $this->prospectApiToken(),
            'prospectApiEndpoint' => route('api.prospects.import'),
            'prospectExtensionUrl' => $extension ? asset('files/'.$extension->getFilename()) : null,
            'prospectExtensionFilename' => $extension?->getFilename(),
            'prospectExtensionSigned' => $extension?->getExtension() === 'xpi',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prospect_api_token' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => Setting::PROSPECT_API_TOKEN],
            ['value' => trim($data['prospect_api_token'])],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Token de integração salvo.',
        ]);

        return to_route('lead-settings.edit');
    }

    private function prospectApiToken(): ?string
    {
        return Setting::valueFor(
            Setting::PROSPECT_API_TOKEN,
            config('services.ab_prospect.token'),
        );
    }
}
