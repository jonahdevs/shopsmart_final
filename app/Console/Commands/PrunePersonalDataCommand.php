<?php

namespace App\Console\Commands;

use App\Models\RecentlyViewed;
use App\Settings\LegalSettings;
use Illuminate\Console\Command;
use Spatie\Activitylog\Support\Config;

/**
 * Enforces the retention windows on {@see LegalSettings}.
 *
 * Two trails in this application accumulate a record of what a person did
 * rather than what they bought: `recently_viewed` (browsing history, per user
 * per product) and the activity log (who changed what in the admin). Neither
 * is a transaction record, so neither has a reason to be kept forever, and a
 * retention setting nothing acts on is worse than no setting at all.
 *
 * Orders are deliberately out of scope. They keep their frozen customer details
 * because they are the store's accounting record.
 */
class PrunePersonalDataCommand extends Command
{
    protected $signature = 'privacy:prune';

    protected $description = 'Delete browsing history and activity log entries past the retention windows in settings';

    public function handle(LegalSettings $settings): int
    {
        $recentlyViewed = $this->pruneRecentlyViewed($settings->recently_viewed_retention_days);
        $activity = $this->pruneActivityLog($settings->activity_log_retention_days);

        $this->components->info(sprintf(
            'Pruned %d recently-viewed row(s) and %d activity log entr(y/ies).',
            $recentlyViewed,
            $activity,
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
