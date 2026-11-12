<?php

namespace App\Models;

use Database\Factories\CustomTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task tag the user added on top of the built-in ones in App\Enums\TaskTag: one lower-case word. Tasks hold the tag's name.
 */
#[Fillable(['name'])]
class CustomTag extends Model
{
    /** @use HasFactory<CustomTagFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
