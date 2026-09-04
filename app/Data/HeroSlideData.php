<?php

namespace App\Data;

use App\Models\HeroSlide;
use App\Support\SafeUrl;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One panel of the home-page hero carousel. Desktop and mobile art are
 * separate images because the wide banner crops badly on a phone.
 */
#[TypeScript]
class HeroSlideData extends Data
{
    public function __construct(
        public int $id,
        public string $headline,
        public ?string $subheadline,
        public ?string $ctaLabel,
        public ?string $ctaUrl,
        public bool $hasCallToAction,
        /** left|center|right — where the text block sits over the art. */
        public string $alignment,
        /** dark|light — light type for dark artwork. */
        public string $textTheme,
        public ?ImageData $desktopImage,
        public ?ImageData $mobileImage,
    ) {}

    public static function fromModel(HeroSlide $slide): self
    {
        $desktop = $slide->getFirstMedia('desktop');
        $mobile = $slide->getFirstMedia('mobile');

        // The carousel binds this straight to an href, so a URL the client
        // must not follow is dropped here — and the call to action goes with
        // it, rather than rendering a button that leads nowhere.
        $ctaUrl = app(SafeUrl::class)->forLink($slide->cta_url);

        return new self(
            id: $slide->getKey(),
            headline: $slide->headline,
            subheadline: $slide->subheadline,
            ctaLabel: $slide->cta_label,
            ctaUrl: $ctaUrl,
            hasCallToAction: $ctaUrl !== null && $slide->hasCallToAction(),
            alignment: $slide->alignment,
            textTheme: $slide->text_theme,
            desktopImage: $desktop === null ? null : ImageData::fromMedia($desktop, $slide->headline, 'wide'),
            mobileImage: $mobile === null ? null : ImageData::fromMedia($mobile, $slide->headline, 'card'),
        );
    }
}
