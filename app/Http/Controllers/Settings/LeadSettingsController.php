<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
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
            'prospectApiToken' => config('services.ab_prospect.token'),
            'prospectApiEndpoint' => route('api.prospects.import'),
            'prospectExtensionUrl' => $extension ? asset('files/'.$extension->getFilename()) : null,
            'prospectExtensionFilename' => $extension?->getFilename(),
            'prospectExtensionSigned' => $extension?->getExtension() === 'xpi',
        ]);
    }
}
