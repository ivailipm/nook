<?php

namespace App\Models;

use Database\Factories\CustomStoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A store the user added on top of the built-in ones in App\Enums\Store. Items and promos hold the store's name.
 */
#[Fillable(['name'])]
class CustomStore extends Model
{
    /** @use HasFactory<CustomStoreFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
