<?php

namespace App\Http\Controllers;

use App\Models\EbookEntitlement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EbookLibraryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('knowledge/library', [
            'items' => EbookEntitlement::query()
                ->where('user_id', $request->user()->id)
                ->with('ebook:id,volume_slug,title,description,version,status,published_at')
                ->latest('granted_at')
                ->get(),
        ]);
    }
}
