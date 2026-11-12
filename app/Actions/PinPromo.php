<?php

namespace App\Actions;

use App\Models\Promo;
use App\Models\User;
use App\Support\RelativeDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Pins a deal to the user's promo board, with its processed photo, and links the chosen list item to it.
 * The item keeps its store. A blank product or deal falls back to the prototype's wording.
 */
class PinPromo
{
    public function __construct(private StorePromoPhoto $storePromoPhoto) {}

    public function handle(
        User $user,
        string $store,
        string $validFrom,
        string $validUntil,
        string $product,
        string $deal,
        string $note,
        ?int $linkItem,
        ?UploadedFile $photo,
    ): Promo {
        $product = Str::trim($product);
        $deal = Str::trim($deal);
        $note = Str::trim($note);

        $promo = $user->promos()->create([
            'store' => $store,
            'product' => $product === '' ? "{$store} deal" : $product,
            'deal' => $deal === '' ? 'Deal' : $deal,
            'note' => $note === '' ? null : $note,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'photo_path' => $photo === null ? null : $this->storePromoPhoto->handle($photo),
        ]);

        if ($linkItem !== null) {
            $user->groceryItems()->whereKey($linkItem)->update(['promo_id' => $promo->id]);
        }

        return $promo;
    }

    /**
     * The toast after pinning: how long the deal runs, or that it has already ended.
     */
    public function message(Promo $promo): string
    {
        return $promo->daysLeft() < 0
            ? 'Pinned to the board · already expired'
            : 'Pinned to the board · until '.RelativeDate::format($promo->valid_until);
    }
}
