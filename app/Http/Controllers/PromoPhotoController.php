<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a promo's photo from the private disk to the user who owns the promo. Everyone else gets a 404.
 */
class PromoPhotoController extends Controller
{
    public function __invoke(Request $request, string $promo): StreamedResponse
    {
        // whereNumber lets through digits too big for a bigint, which Postgres would answer with an error and a 500.
        abort_if(filter_var($promo, FILTER_VALIDATE_INT) === false, 404);

        $path = $request->user()->promos()->findOrFail($promo)->photo_path;
        $disk = Storage::disk(Promo::PHOTO_DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
