<?php

namespace App\Jobs;

use App\Http\Middleware\TrackVisitor;
use App\Models\Visitor;
use App\Support\Http\UserAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes the one row a browsing session earns.
 *
 * Queued because nothing on the page depends on it. {@see TrackVisitor} decides
 * *whether* a visit counts — that decision needs the request — and everything
 * left after it is a user-agent string to pick apart and an insert, neither of
 * which a shopper should wait for. On a `sync` queue this simply runs inline,
 * which is why the middleware's gate has to be the real gate.
 *
 * The header is parsed here rather than in the middleware for the same reason:
 * the request path should carry as little of this as possible.
 */
class RecordVisit implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $trackingId,
        /** False when the browser arrived already carrying a tracking cookie. */
        private readonly bool $isNew,
        private readonly ?string $ip,
        private readonly ?string $agent,
        private readonly ?string $country,
    ) {}

    public function handle(): void
    {
        Visitor::query()->create([
            'tracking_id' => $this->trackingId,
            'is_new' => $this->isNew,
            'ip' => $this->ip,
            'browser' => UserAgent::browser($this->agent),
            'platform' => UserAgent::platform($this->agent),
            'country' => $this->country,
        ]);
    }
}
