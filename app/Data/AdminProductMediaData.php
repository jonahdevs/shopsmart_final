<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One image in the product editor's gallery manager.
 *
 * Deliberately not {@see ImageData}: that object resolves every storefront
 * rendition and carries no id, because a shopper never addresses an individual
 * file. The editor does — it has a delete button per image — so this carries
 * the media id and one small preview, and nothing else.
 *
 * Conversions are queued, so `thumbUrl` falls back to the original rather than
 * pointing at a 404 while they are still pending.
 */
#[TypeScript]
class AdminProductMediaData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
        public string $thumbUrl,
        public int $orderColumn,
    ) {}

    public static function fromMedia(Media $media): self
    {
        return new self(
            id: (int) $media->getKey(),
            name: $media->name,
            url: $media->getUrl(),
            thumbUrl: $media->hasGeneratedConversion('thumb')
                ? $media->getUrl('thumb')
                : $media->getUrl(),
            orderColumn: (int) ($media->order_column ?? 0),
        );
    }
}
