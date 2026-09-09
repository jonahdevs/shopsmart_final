<?php

namespace App\Http\Middleware;

use App\Enums\ConsentCategory;
use App\Jobs\RecordVisit;
use App\Support\Consent;
use App\Support\Http\UserAgent;
use App\Support\Http\VisitorCountry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts browsing sessions, for the visitor panels on the admin dashboard.
 *
 * Three decisions are worth stating outright.
 *
 * **It is gated on analytics consent, through {@see Consent} and nothing else.**
 * This records an IP address and a country, which is personal data by any
 * reading, and it sets a cookie to recognise the browser again — so it is the
 * same kind of thing as the Google and Meta tags and answers to the same gate.
 * A visitor who has not granted analytics gets no row *and no cookie*: the
 * cookie is itself the tracking, so minting one and then declining to use it
 * would be the whole intrusion with none of the benefit. If the store does not
 * offer the analytics category at all, nobody can grant it and this is off for
 * everyone — exactly as the measurement tags are.
 *
 * **One row per session, not per page view.** A visitor reading nine pages is
 * one visit. The session is what says so: the tracking cookie outlives the
 * session and identifies the browser (which is what makes "returning"
 * answerable), while a flag in the session says this visit has already been
 * counted. Neither costs a query.
 *
 * **The work happens after the response is built and then leaves the request.**
 * The checks below are ordered cheapest first, and the only one that touches
 * anything beyond the request object is the consent read — which resolves the
 * same cached read model the document head already asked for. Everything past
 * the gate is handed to a queued job.
 */
class TrackVisitor
{
    /**
     * The browser's pseudonymous id. Encrypted with every other cookie in this
     * application — it is written server-side and never read by JavaScript, so
     * it has no reason to travel in the clear.
     */
    public const COOKIE = 'visitor';

    /** How long a browser stays recognisable, in days. */
    private const COOKIE_DAYS = 180;

    /** Set for the life of the session once this visit has been counted. */
    private const SESSION_KEY = 'visitor.counted';

    /**
     * Paths that are never a visit.
     *
     * The admin panel is staff at work, not audience, and counting it would put
     * the shop's own team in its traffic figures. The health check and the
     * crawler files are machine endpoints that happen to answer 200 to a GET.
     *
     * @var list<string>
     */
    private const IGNORED = [
        'admin',
        'admin/*',
        'up',
        'robots.txt',
        'sitemap.xml',
        'api/*',
    ];

    public function __construct(private Consent $consent) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->counts($request, $response)) {
            $this->record($request);
        }

        return $response;
    }

    /**
     * Whether this request represents a person looking at a page of the shop.
     *
     * A partial reload is the page the visitor is already on fetching one prop,
     * so it carries `X-Inertia-Partial-Component` and is not a second visit —
     * without this check the deferred props on any page would each count.
     */
    private function counts(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && ! $request->expectsJson()
            && ! $request->hasHeader('X-Inertia-Partial-Component')
            && ! $request->is(...self::IGNORED)
            && $request->hasSession()
            && $request->session()->get(self::SESSION_KEY) !== true
            && ! UserAgent::isRobot($request->userAgent())
            && $this->consent->allows($request, ConsentCategory::Analytics);
    }

    private function record(Request $request): void
    {
        $existing = $request->cookie(self::COOKIE);
        $tracking = is_string($existing) && Str::isUuid($existing) ? $existing : null;
        $isNew = $tracking === null;

        if ($tracking === null) {
            $tracking = (string) Str::uuid();

            Cookie::queue(Cookie::make(
                self::COOKIE,
                $tracking,
                self::COOKIE_DAYS * 24 * 60,
            ));
        }

        // The cookie is the answer to "have we met". A row already exists for
        // this browser if and only if it arrived carrying one, so the reference
        // build's existence query buys nothing that the cookie does not already
        // say — and it ran on every first page view of every session.
        RecordVisit::dispatch(
            trackingId: $tracking,
            isNew: $isNew,
            ip: $request->ip(),
            agent: $request->userAgent(),
            country: VisitorCountry::for($request),
        );

        // Set after dispatch, not before: this middleware sits inside
        // StartSession, so the flag is written while the session is still open
        // and is persisted as the response unwinds.
        $request->session()->put(self::SESSION_KEY, true);
    }
}
