<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\ContentAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Análise editorial em tempo real do formulário do artigo. */
class AnalyzeController extends Controller
{
    public function __invoke(Request $request, ContentAnalyzer $analyzer): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:500000'],
            'focus_keyword' => ['nullable', 'string', 'max:120'],
            'post_id' => ['nullable', 'integer'],
        ]);

        return response()->json($analyzer->analyze($data));
    }
}
