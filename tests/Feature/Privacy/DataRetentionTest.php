<?php

use App\Models\Product;
use App\Models\RecentlyViewed;
use App\Models\User;
use App\Settings\LegalSettings;
use Spatie\Activitylog\Support\Config;

/**
 * `privacy:prune` enforcing the retention windows on LegalSettings.
 *
 * Both trails it touches record what a person did rather than what they bought.
 * Orders are deliberately not in scope: they keep their frozen customer details
 * because they are the store's accounting record, and this command must not
 * quietly start deleting them.
 */
function useRetention(int $recentlyViewedDays, int $activityDays): void
{
    $legal = app(LegalSettings::class);
    $legal->recently_viewed_retention_days = $recentlyViewedDays;
    $legal->activity_log_retention_days = $activityDays;
    $legal->save();
}

/** A browsing-history row last touched this many days ago. */
function viewedDaysAgo(User $user, Product $product, int $days): RecentlyViewed
{
    return RecentlyViewed::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'viewed_at' => now()->subDays($days),
    ]);
}

/** An activity log entry recorded this many days ago. */
function loggedDaysAgo(string $description, int $days): void
{
    activity()->log($description);

    Config::activityModel()::query()
        ->where('description', $description)
        ->update(['created_at' => now()->subDays($days)]);
}

test('browsing history older than the window is deleted and newer history is kept', function () {
    useRetention(30, 0);

    $user = User::factory()->create();
    $stale = viewedDaysAgo($user, Product::factory()->create(), 31);
    $fresh = viewedDaysAgo($user, Product::factory()->create(), 29);

    $this->artisan('privacy:prune')->assertSuccessful();

    $this->assertDatabaseMissing('recently_viewed', ['id' => $stale->id]);
    $this->assertModelExists($fresh);
});

test('a zero window keeps browsing history indefinitely', function () {
    useRetention(0, 0);

    $ancient = viewedDaysAgo(User::factory()->create(), Product::factory()->create(), 4000);

    $this->artisan('privacy:prune')->assertSuccessful();

    $this->assertModelExists($ancient);
});

test('activity log entries older than the window are deleted', function () {
    useRetention(0, 90);

    loggedDaysAgo('an old admin action', 120);
    loggedDaysAgo('a recent admin action', 30);

    $this->artisan('privacy:prune')->assertSuccessful();

    $this->assertDatabaseMissing('activity_log', ['description' => 'an old admin action']);
    $this->assertDatabaseHas('activity_log', ['description' => 'a recent admin action']);
});

test('a zero window keeps the activity log indefinitely', function () {
    useRetention(0, 0);

    loggedDaysAgo('an old admin action', 4000);

    $this->artisan('privacy:prune')->assertSuccessful();

    $this->assertDatabaseHas('activity_log', ['description' => 'an old admin action']);
});
