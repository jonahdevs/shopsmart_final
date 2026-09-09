<?php

namespace App\Console\Commands;

use App\Models\RecentlyViewed;
use App\Models\Visitor;
use App\Settings\LegalSettings;
use Illuminate\Console\Command;
use Spatie\Activitylog\Support\Config;

/**
 * Enforces the retention windows on {@see LegalSettings}.
 *
 * Three trails in this application accumulate a record of what a person did
 * rather than what they bought: `recently_viewed` (browsing history, per user
 * per product), the activity log (who changed what in the admin) and `visitors`
 * (one row per browsing session, with the IP it came from). None is a
 * transaction record, so none has a reason to be kept forever, and a retention
 * setting nothing acts on is worse than no setting at all.
 *
 * Orders are deliberately out of scope. They keep their frozen customer details
 * because they are the store's accounting record.
 */
class PrunePersonalDataCommand extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Delete browsing history, activity log entries and visitor sessions past the retention windows in settings';

    public function handle(LegalSettings $settings): int
    {
        $recentlyViewed = $this->pruneRecentlyViewed($settings->recently_viewed_retention_days);
        $activity = $this->pruneActivityLog($settings->activity_log_retention_days);
        $visitors = $this->pruneVisitors($settings->visitor_retention_days);

        $this->components->info(sprintf(
            'Deleted %d browsing history row(s), %d activity log record(s) and %d visitor session(s).',
            $recentlyViewed,
            $activity,
            $visitors,
        ));

        return self::SUCCESS;
    }

    /**
     * A window of zero means "keep indefinitely", so nothing is deleted.
     */
    private function pruneRecentlyViewed(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return RecentlyViewed::query()
            ->where('viewed_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Visitor sessions age out on `created_at` — the row is never updated after
     * it is written, so the two timestamps say the same thing and the indexed
     * one is the one to filter on.
     */
    private function pruneVisitors(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return Visitor::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Delegated to the package's own action rather than a hand-rolled delete,
     * so a store that swaps in its own activity model or clean action still
     * gets pruned by the same code path `activitylog:clean` uses.
     */
    private function pruneActivityLog(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return Config::cleanActivityLogAction()->execute($days);
    }
}
