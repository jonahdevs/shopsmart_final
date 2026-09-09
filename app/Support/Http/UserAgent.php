<?php

namespace App\Support\Http;

/**
 * What can be read off a `User-Agent` header without asking a package.
 *
 * A user-agent string is a compatibility fiction — every browser claims to be
 * several others — so no parser is exact and a heavier one is not more exact,
 * only more detailed. The dashboard asks two coarse questions of it (which
 * browser, which platform) and one gate question (is this a robot), and needle
 * matching in a fixed order answers all three. That is worth more than a
 * dependency whose regex database has to be kept current.
 *
 * Phone-against-desk is deliberately not a fourth question. The platform
 * already says it — Android and iPhone are hand-held, Windows and macOS are
 * not — so a second column repeating it would be a second answer that can
 * disagree with the first.
 *
 * Order is the whole design. Edge and Opera are Chrome and say so, Chrome
 * claims Safari, and Samsung Internet claims both — so the most specific claim
 * has to be tested first, and the list below reads from most to least specific
 * rather than alphabetically. Reordering it silently mislabels a browser.
 */
final class UserAgent
{
    /**
     * Browser tokens, most specific claim first.
     *
     * @var array<string, list<string>>
     */
    private const BROWSERS = [
        'Edge' => ['Edg/', 'Edge/'],
        'Opera' => ['OPR/', 'Opera'],
        'Samsung Internet' => ['SamsungBrowser'],
        'Firefox' => ['Firefox/', 'FxiOS'],
        'Chrome' => ['Chrome/', 'CriOS'],
        'Safari' => ['Safari/'],
    ];

    /**
     * Platform tokens. The device names come before the operating systems that
     * contain them, because an iPhone is also "Mac OS X" to itself.
     *
     * @var array<string, list<string>>
     */
    private const PLATFORMS = [
        'iPhone' => ['iPhone'],
        'iPad' => ['iPad'],
        'Android' => ['Android'],
        'Windows' => ['Windows'],
        'macOS' => ['Macintosh', 'Mac OS X'],
        'Linux' => ['Linux'],
    ];

    /**
     * Anything that is not a person browsing.
     *
     * Deliberately broad and deliberately including the HTTP clients — curl,
     * Guzzle, Postman — as well as the search crawlers. A dashboard that counts
     * an uptime monitor as a visitor is not reporting an audience, and the cost
     * of over-matching here is a missing row, not a wrong one.
     *
     * @var list<string>
     */
    private const ROBOTS = [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'curl/',
        'wget',
        'python-requests',
        'python-urllib',
        'scrapy',
        'headlesschrome',
        'phantomjs',
        'facebookexternalhit',
        'embedly',
        'pingdom',
        'lighthouse',
        'go-http-client',
        'okhttp',
        'guzzlehttp',
        'java/',
        'libwww-perl',
        'postmanruntime',
        'axios/',
        'node-fetch',
        'apachebench',
        'ia_archiver',
        'baiduspider',
        'yandex',
        'ahrefs',
        'semrush',
    ];

    public static function browser(?string $agent): ?string
    {
        return self::firstMatch($agent, self::BROWSERS);
    }

    public static function platform(?string $agent): ?string
    {
        return self::firstMatch($agent, self::PLATFORMS);
    }

    /**
     * An empty or absent header counts as a robot. Every real browser sends
     * one, so its absence is a script that did not bother — and the safe
     * failure for a visitor count is to drop a row rather than invent a person.
     */
    public static function isRobot(?string $agent): bool
    {
        if ($agent === null || trim($agent) === '') {
            return true;
        }

        $agent = mb_strtolower($agent);

        foreach (self::ROBOTS as $needle) {
            if (str_contains($agent, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $candidates
     */
    private static function firstMatch(?string $agent, array $candidates): ?string
    {
        if ($agent === null || trim($agent) === '') {
            return null;
        }

        foreach ($candidates as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($agent, $needle)) {
                    return $name;
                }
            }
        }

        return null;
    }
}
