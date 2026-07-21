<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlogCommentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/comments', ['comments' => BlogComment::with(['post:id,title,slug', 'user:id,name,email'])->latest()->paginate(20)]);
    }

    public function approve(BlogComment $comment): RedirectResponse
    {
        $comment->update(['status' => 'approved', 'approved_at' => now()]);

        return back();
    }

    public function reject(BlogComment $comment): RedirectResponse
    {
        $comment->update(['status' => 'rejected', 'approved_at' => null]);

        return back();
    }

    public function destroy(BlogComment $comment): RedirectResponse
    {
        $comment->delete();

        return back();
    }
}
