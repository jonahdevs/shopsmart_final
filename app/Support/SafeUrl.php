<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Guards a stored URL before it is handed to the client as a link target.
 *
 * `hero_slides.cta_url` is bound straight to an `href`, and a `javascript:` or
 * `data:` value there executes on click. Filtering on the way out — rather than
 * only in the form that writes the column — keeps the guarantee whatever wrote
 * the row.
 */
class SafeUrl
{
    /** Schemes that are safe to put behind a storefront link. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Return the URL when it is a same-site path or carries an allow-listed
     * scheme, and null otherwise.
     */
    public function forLink(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // A protocol-relative URL (`//evil.test`) reads as a path but resolves
        // to another origin, so it is not treated as same-site — and browsers
        // normalise a leading backslash to a slash, so `/\evil.test` is the
        // same trick wearing a different hat.
        if (Str::startsWith($url, '/') && ! Str::startsWith($url, ['//', '/\\'])) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return null;
        }

        return in_array(Str::lower($scheme), self::ALLOWED_SCHEMES, true) ? $url : null;
    }
}
