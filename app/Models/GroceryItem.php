<?php

namespace App\Models;

use App\Enums\GroceryCategory;
use Database\Factories\GroceryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'qty', 'store', 'category', 'done', 'promo_id'])]
class GroceryItem extends Model
{
    /** @use HasFactory<GroceryItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => GroceryCategory::class,
            'done' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Promo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    /**
     * Whether the item is linked to a promo that hasn't ended, worked out from today's date
     * so a link left over until the nightly nook:expire-promos run never counts.
     */
    public function hasActivePromo(): bool
    {
        return $this->promo !== null && $this->promo->daysLeft() >= 0;
    }
}
