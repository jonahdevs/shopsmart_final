<?php

namespace App\Models;

use App\Jobs\RecordVisit;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One browsing session on the storefront.
 *
 * Written by {@see RecordVisit}, once per session, and only for a
 * visitor who granted analytics consent. Nothing here identifies a person:
 * `tracking_id` is a random uuid the browser carries, and there is no foreign
 * key to `users` even for a signed-in shopper — the dashboard asks how many
 * people came, never which of them it was.
 *
 * `ip` is the one column that could be tied back to somebody, which is why the
 * whole table has a retention window on the privacy screen and is pruned
 * nightly by `privacy:prune`.
 *
 * @property int $id
 * @property string $tracking_id
 * @property bool $is_new First session under this tracking id.
 * @property string|null $ip
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $country ISO 3166-1 alpha-2, when the edge could place it.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tracking_id', 'is_new', 'ip', 'browser', 'platform', 'country',
])]
class Visitor extends Model
{
    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_new' => 'boolean',
        ];
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * Sessions inside a window. Every dashboard figure is a fraction of the
     * same window, so the bound belongs in one scope rather than in each
     * aggregate.
     *
     * @param  Builder<Visitor>  $query
     */
    #[Scope]
    protected function between(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }
}
