<?php

namespace App\Data;

use App\Enums\DateRangePreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One named window a date picker may offer.
 *
 * A list rather than a `key => label` map because the order is the whole point
 * — shortest window first — and a map hands the client an object whose key
 * order it has to trust rather than an array it can simply render.
 */
#[TypeScript]
class AdminDateRangeOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    public static function fromPreset(DateRangePreset $preset): self
    {
        return new self(
            value: $preset->value,
            label: $preset->label(),
        );
    }
}
