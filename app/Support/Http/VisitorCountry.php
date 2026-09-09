<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Locale;

/**
 * Where a visit came from, as a two-letter country code.
 *
 * There is no IP database here and none is wanted: shipping a GeoIP file would
 * be a dependency that goes stale, and calling a lookup service would send a
 * shopper's IP address to a third party — which is precisely the thing the
 * consent gate exists to prevent.
 *
 * So the country is whatever the edge already worked out. Cloudflare, Fastly
 * and every other CDN in front of an origin will resolve it and add a header;
 * if nothing is in front of this application, the answer is simply null and the
 * dashboard says "unknown".
 *
 * That header is only believed when the request reached us through a trusted
 * proxy. A header is client-supplied until a proxy overwrites it, so trusting
 * it unconditionally would let any visitor type their own country into the
 * store's analytics. `trustProxies()` in bootstrap/app.php is the same
 * configuration that decides whether `X-Forwarded-For` is believed, and the
 * same answer applies here.
 */
final class VisitorCountry
{
    /**
     * Edge country headers, in the order they are consulted.
     *
     * @var list<string>
     */
    private const HEADERS = [
        'CF-IPCountry',
        'CloudFront-Viewer-Country',
        'X-Vercel-IP-Country',
        'Fastly-Client-Country',
        'X-Country-Code',
    ];

    /**
     * Codes an edge returns when it could not place the address at all — a Tor
     * exit node, or a reserved range. Recorded as null rather than as a country
     * nobody lives in.
     *
     * @var list<string>
     */
    private const UNPLACEABLE = ['XX', 'T1'];

    /**
     * The country's English name, for a dashboard that has to label a region.
     *
     * `intl` already ships the CLDR region names, so there is no table of two
     * hundred countries to maintain here and none to fall out of date. Where
     * the extension is absent the code itself is the label: "KE" is a poorer
     * heading than "Kenya" but it is never a wrong one, and the map shades the
     * region either way.
     */
    public static function name(string $country): string
    {
        if (! extension_loaded('intl')) {
            return $country;
        }

        $name = Locale::getDisplayRegion('-'.$country, 'en');

        return $name === '' || $name === false ? $country : $name;
    }

    public static function for(Request $request): ?string
    {
        if (! $request->isFromTrustedProxy()) {
            return null;
        }

        foreach (self::HEADERS as $header) {
            $country = self::normalise($request->header($header));

            if ($country !== null && ! in_array($country, self::UNPLACEABLE, true)) {
                return $country;
            }
        }

        return null;
    }

    private static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $country = mb_strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }
}
