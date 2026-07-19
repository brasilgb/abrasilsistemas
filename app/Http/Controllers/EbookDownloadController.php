<?php

namespace App\Http\Controllers;

use App\Models\Ebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbookDownloadController extends Controller
{
    public function __invoke(Request $request, Ebook $ebook): StreamedResponse
    {
        abort_unless(
            $ebook->entitlements()->where('user_id', $request->user()->id)->exists(),
            403,
        );
        abort_if($ebook->file_path === null || ! Storage::disk('local')->exists($ebook->file_path), 404);

        return Storage::disk('local')->download(
            $ebook->file_path,
            str($ebook->title)->slug().'-v'.$ebook->version.'.pdf',
        );
    }
}
