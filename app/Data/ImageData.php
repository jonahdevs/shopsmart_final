<?php

namespace App\Data;

use Illuminate\Support\Facades\Cache;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One image, pre-resolved into every rendition the storefront renders, so a
 * Vue component never has to know a conversion name.
 *
 * Conversions are queued, so any of them may still be missing right after an
 * import; each falls back to null (or to the original file for `url`) rather
 * than pointing at a 404.
 */
#[TypeScript]
class ImageData extends Data
{
    public function __construct(
        public string $url,
        public ?string $webpUrl,
        public ?string $zoomUrl,
        public ?string $zoomWebpUrl,
        public ?string $thumbUrl,
        /** Inline base64 blur-up placeholder, null until the lqip conversion exists. */
        public ?string $placeholder,
        public string $alt,
        public bool $isCover,
    ) {}

    /**
     * @param  string  $primaryConversion  The conversion `url` should prefer; the
     *                                     original file is used when it is missing.
     */
    public static function fromMedia(Media $media, string $alt, string $primaryConversion = 'card'): self
    {
        return new self(
            url: self::conversionUrl($media, $primaryConversion) ?? $media->getUrl(),
            webpUrl: self::conversionUrl($media, 'card-webp'),
            zoomUrl: self::conversionUrl($media, 'zoom'),
            zoomWebpUrl: self::conversionUrl($media, 'zoom-webp'),
            thumbUrl: self::conversionUrl($media, 'thumb'),
            placeholder: self::placeholder($media),
            alt: $alt,
            isCover: (bool) $media->getCustomProperty('is_cover', false),
        );
    }

    private static function conversionUrl(Media $media, string $conversion): ?string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : null;
    }

    /**
     * The lqip rendition inlined as a data URI. Kept in the cache because the
     * bytes never change for a given media revision, and a listing would
     * otherwise re-read one tiny file per tile on every request.
     */
    private static function placeholder(Media $media): ?string
    {
        if (! $media->hasGeneratedConversion('lqip')) {
            return null;
        }

        return Cache::rememberForever(
            "media-lqip:{$media->getKey()}:{$media->updated_at?->getTimestamp()}",
            function () use ($media): ?string {
                $path = $media->getPath('lqip');

                if (! is_file($path)) {
                    return null;
                }

                $contents = file_get_contents($path);

                return $contents === false ? null : 'data:image/jpeg;base64,'.base64_encode($contents);
            },
        );
    }
}
