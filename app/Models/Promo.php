<?php

namespace App\Models;

use App\Enums\Store;
use Carbon\CarbonInterface;
use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['store', 'product', 'deal', 'note', 'valid_from', 'valid_until', 'photo_path', 'tone'])]
class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory;

    /**
     * The private disk that holds the promo photos.
     */
    public const string PHOTO_DISK = 'local';

    protected static function booted(): void
    {
        // Without a tone, a new promo takes the next of the six polaroid tones after the user's latest promo.
        static::creating(function (Promo $promo) {
            if ($promo->getAttribute('tone') !== null) {
                return;
            }

            $latest = static::where('user_id', $promo->user_id)->orderByDesc('id')->value('tone');

            $promo->tone = $latest === null ? 0 : ($latest + 1) % 6;
        });

        static::deleted(function (Promo $promo) {
            if ($promo->photo_path !== null) {
                Storage::disk(self::PHOTO_DISK)->delete($promo->photo_path);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store' => Store::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'tone' => 'integer',
        ];
    }

    /**
     * Promos that end today or later.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('valid_until', '>=', today()->toDateString());
    }

    /**
     * Promos that ended before today.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->where('valid_until', '<', today()->toDateString());
    }

    /**
     * The archive: promos that expired (the day after they ended) during this Monday-to-Sunday week.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expiredThisWeek(Builder $query): void
    {
        $query->whereBetween('valid_until', [
            today()->startOfWeek(CarbonInterface::MONDAY)->subDay()->toDateString(),
            today()->subDay()->toDateString(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<GroceryItem, $this>
     */
    public function groceryItems(): HasMany
    {
        return $this->hasMany(GroceryItem::class);
    }
}
