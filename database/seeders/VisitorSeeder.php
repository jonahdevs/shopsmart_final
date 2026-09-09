<?php

namespace Database\Seeders;

use App\Models\Visitor;
use App\Support\Http\VisitorCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Six weeks of browsing sessions, so the dashboard's visitor panels have a
 * shape to draw locally.
 *
 * The dashboard reads a trailing thirty days; this seeds forty-two so the
 * retention command has rows outside the window to prune and the panels are not
 * accidentally showing the whole table.
 *
 * Three things are shaped rather than random, because a uniform series makes
 * every panel look identical and hides the difference between a working chart
 * and a broken one: weekends are quieter than weekdays, a share of each day's
 * sessions reuse a tracking id already in the pool, which is what makes
 * `is_new` mean something rather than being a coin toss per row, and the
 * countries come out Kenya-heavy with a long thin tail so the map has both ends
 * of its shading ramp in use.
 *
 * The countries are the reason this seeder matters more than most. A real visit
 * only carries one when the request reached the application through a trusted
 * proxy that resolved it — see {@see VisitorCountry} — which
 * on a developer's machine is never. Without seeded rows the map would be blank
 * on every local install and would look broken rather than unconfigured. A few
 * sessions are left unplaced anyway, because that is what an edge does with a
 * Tor exit or a reserved range, and the panels have to be right about the
 * difference between "nobody came from there" and "we could not tell".
 */
class VisitorSeeder extends Seeder
{
    /** Days of history to lay down. */
    private const DAYS = 42;

    /** Sessions on an ordinary weekday, before the daily jitter. */
    private const WEEKDAY_SESSIONS = 34;

    /** Percentage of sessions that come from a browser already in the pool. */
    private const RETURNING_PERCENT = 45;

    /** Percentage of sessions an edge would not have been able to place. */
    private const UNPLACED_PERCENT = 4;

    /** Rows per insert: one round trip, comfortably under SQLite's parameter cap. */
    private const CHUNK = 200;

    /**
     * Tracking ids seen so far, which is what a returning session draws from.
     *
     * @var list<string>
     */
    private array $known = [];

    public function run(): void
    {
        $rows = [];

        for ($offset = self::DAYS; $offset >= 0; $offset--) {
            $day = Carbon::now()->subDays($offset);

            foreach ($this->sessionsFor($day) as $at) {
                $rows[] = $this->session($at);
            }
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('visitors')->insert($chunk);
        }

        $this->command->info(sprintf(
            'Seeded %d visitor session(s) across %d days.',
            count($rows),
            self::DAYS + 1,
        ));
    }

    /**
     * The moments a given day was visited.
     *
     * Scattered through the shopping day rather than dropped on midnight, so
     * anything that later buckets these by hour has something real to bucket.
     *
     * @return list<Carbon>
     */
    private function sessionsFor(Carbon $day): array
    {
        $base = $day->isWeekend()
            ? (int) round(self::WEEKDAY_SESSIONS * 0.6)
            : self::WEEKDAY_SESSIONS;

        $count = max(1, $base + random_int(-8, 8));

        return array_map(
            static fn (): Carbon => $day->copy()
                ->setTime(random_int(7, 22), random_int(0, 59), random_int(0, 59)),
            range(1, $count),
        );
    }

    /**
     * One row, built through the factory so the seeder and the tests agree on
     * what a plausible session looks like — the factory is what keeps a browser
     * paired with a platform it actually runs on.
     *
     * @return array<string, mixed>
     */
    private function session(Carbon $at): array
    {
        $returning = $this->known !== [] && random_int(1, 100) <= self::RETURNING_PERCENT;

        $factory = Visitor::factory()->on($at);

        if (random_int(1, 100) <= self::UNPLACED_PERCENT) {
            $factory = $factory->unplaced();
        }

        /** @var Visitor $visitor */
        $visitor = $factory
            ->state($returning
                ? ['is_new' => false, 'tracking_id' => $this->known[array_rand($this->known)]]
                : ['is_new' => true])
            ->make();

        if (! $returning) {
            $this->known[] = (string) $visitor->tracking_id;
        }

        return $visitor->getAttributes();
    }
}
