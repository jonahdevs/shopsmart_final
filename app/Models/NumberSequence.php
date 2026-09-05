<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A named counter handing out the next number in a series.
 *
 * Order numbers are shown to customers and quoted back to support, so they must
 * be unique and must not look random. `next()` takes a row lock for the few
 * microseconds it needs to read and bump the value, which is why callers should
 * take their number BEFORE opening the transaction that uses it — holding this
 * lock across a batch of inserts serialises the whole checkout.
 *
 * Numbers are unique but not contiguous: a rolled-back transaction burns the
 * one it took. That is the accepted trade — reissuing a number is far worse
 * than skipping one.
 *
 * @property string $key
 * @property int $next_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'next_value'])]
class NumberSequence extends Model
{
    /** How many times a deadlocked increment is retried before giving up. */
    private const MAX_ATTEMPTS = 3;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }

    // ==================================================
    // HELPERS
    // ==================================================

    /**
     * Take the next number in the series, creating the series on first use.
     *
     * `insertOrIgnore` makes creation race-free through the primary key rather
     * than through a check-then-insert, and `lockForUpdate` serialises the
     * increment itself. On SQLite — the test connection — the lock compiles
     * away, which is harmless because the suite is single-threaded.
     */
    public static function next(string $key): int
    {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($key): int {
                    DB::table('number_sequences')->insertOrIgnore([
                        'key' => $key,
                        'next_value' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $current = (int) DB::table('number_sequences')
                        ->where('key', $key)
                        ->lockForUpdate()
                        ->value('next_value');

                    DB::table('number_sequences')
                        ->where('key', $key)
                        ->update(['next_value' => $current + 1, 'updated_at' => now()]);

                    return $current;
                });
            } catch (QueryException $e) {
                if (++$attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
