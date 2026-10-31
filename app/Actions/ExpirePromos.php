<?php

namespace App\Actions;

use App\Models\GroceryItem;
use App\Models\Promo;

/**
 * The daily clean-up for every user: grocery items lose their link to expired promos,
 * and promos are deleted, with their photos, 30 days after they ended.
 */
class ExpirePromos
{
    /**
     * @return array{unlinked: int, deleted: int}
     */
    public function handle(): array
    {
        $unlinked = GroceryItem::whereIn('promo_id', Promo::expired()->select('id'))->update(['promo_id' => null]);

        // One at a time, so each promo's deleted event removes its photo.
        $promos = Promo::where('valid_until', '<=', today()->subDays(30)->toDateString())->get();

        foreach ($promos as $promo) {
            $promo->delete();
        }

        return ['unlinked' => $unlinked, 'deleted' => $promos->count()];
    }
}
