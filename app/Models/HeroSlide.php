<?php

namespace App\Models;

use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One panel of the home-page hero carousel.
 *
 * The reference app hardcoded these in the Blade view; here they are editable
 * rows, so a campaign slide can be queued ahead of time and expire on its own
 * without anyone deploying.
 *
 * @property int $id
 * @property string $headline
 * @property string|null $subheadline
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property string $alignment Where the text block sits over the art: left|center|right.
 * @property string $text_theme dark|light — light type for dark artwork.
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Media> $media
 * @property-read int|null $media_count
 */
#[Fillable([
    'headline', 'subheadline', 'cta_label', 'cta_url', 'alignment',
    'text_theme', 'sort_order', 'is_active', 'starts_at', 'ends_at',
])]
class HeroSlide extends Model implements HasMedia
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    // ==================================================
    // MEDIA
    // ==================================================

    /**
     * A slide carries separate desktop and mobile art — the wide banner crops
     * badly on a phone — and each is replaced rather than appended to.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('desktop')->singleFile();
        $this->addMediaCollection('mobile')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Conversions default to JPG, which has no alpha channel, so a
        // transparent source PNG would otherwise flatten onto black. Flattening
        // onto white keeps every rendition consistent with the white image
        // containers used across the storefront.
        //
        // Queued like Product's and Category's conversions: hero art is large,
        // and generating it inline would add minutes to `migrate:fresh --seed`.
        $this->addMediaConversion('wide')
            ->performOnCollections('desktop', 'mobile')
            ->background('ffffff')
            ->fit(Fit::Crop, 2181, 624);

        $this->addMediaConversion('card')
            ->performOnCollections('desktop', 'mobile')
            ->background('ffffff')
            ->fit(Fit::Crop, 1200, 900);

        // Blur-up placeholder shown while the real image loads.
        $this->addMediaConversion('lqip')
            ->performOnCollections('desktop', 'mobile')
            ->background('ffffff')
            ->width(64)
            ->quality(20);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Slides the storefront should show right now: active, and inside their
     * scheduling window. A null bound means "no bound", so a slide with both
     * dates null is live for as long as it is active.
     *
     * @param  Builder<HeroSlide>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->where(fn (Builder $started) => $started
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $notEnded) => $notEnded
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /** A call to action only renders when both halves of it are present. */
    public function hasCallToAction(): bool
    {
        return $this->cta_label !== null && $this->cta_label !== ''
            && $this->cta_url !== null && $this->cta_url !== '';
    }
}
