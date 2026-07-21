<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogCommentController extends Controller
{
    public function store(Request $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $validated = $request->validate(['body' => ['required', 'string', 'min:3', 'max:2000']]);
        $post->comments()->create([...$validated, 'user_id' => $request->user()->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comentário enviado para moderação.']);

        return back();
    }
}
