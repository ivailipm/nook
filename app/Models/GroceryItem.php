<?php

namespace App\Models;

use App\Enums\GroceryCategory;
use App\Enums\Store;
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
            'store' => Store::class,
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
}
