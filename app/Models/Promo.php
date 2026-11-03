<?php

namespace App\Models;

use App\Enums\Store;
use App\Support\RelativeDate;
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
use Illuminate\Support\Str;

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
     * Promos that end within 3 days: from today to 3 days ahead, inclusive.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function endingSoon(Builder $query): void
    {
        $query->whereBetween('valid_until', [today()->toDateString(), today()->addDays(3)->toDateString()]);
    }

    /**
     * Whole calendar days left: 0 on the last day, 1 the day before, negative once expired.
     */
    public function daysLeft(): int
    {
        return RelativeDate::daysFromToday($this->valid_until);
    }

    /**
     * The handwritten label on the polaroid: "ends today!", "ends tomorrow", "until Sat" or "expired 27 Oct".
     */
    public function untilLabel(): string
    {
        $days = $this->daysLeft();

        return match (true) {
            $days < 0 => 'expired '.RelativeDate::format($this->valid_until),
            $days === 0 => 'ends today!',
            $days === 1 => 'ends tomorrow',
            default => 'until '.RelativeDate::format($this->valid_until),
        };
    }

    /**
     * The promo sheet's line: "Colruyt · valid 27 Oct – Sat", or "Colruyt · valid until Sat" without a start date.
     */
    public function validityLine(): string
    {
        $until = RelativeDate::format($this->valid_until);

        return $this->valid_from === null
            ? "{$this->store->value} · valid until {$until}"
            : "{$this->store->value} · valid ".RelativeDate::format($this->valid_from)." – {$until}";
    }

    /**
     * The name of the item "Add to my list" creates: the product without its trailing size, or "Lidl deal" without a product.
     * A product that is only a size keeps its text.
     */
    public function listItemName(): string
    {
        if ($this->product === null) {
            return "{$this->store->value} deal";
        }

        $name = Str::trim((string) preg_replace('/\s*[×x]?\d+\s*(kg|g|l)?\s*$/iu', '', $this->product));

        return $name === '' ? $this->product : $name;
    }

    /**
     * The URL of the owner-checked route that serves the photo, or null without a photo.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : route('promos.photo', $this);
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
