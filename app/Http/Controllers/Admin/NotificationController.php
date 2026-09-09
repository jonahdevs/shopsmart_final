<?php

namespace App\Http\Controllers\Admin;

use App\Data\AdminNotificationData;
use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The notification bell's writes.
 *
 * There is no index action. The bell's contents ride the shared props
 * ({@see HandleInertiaRequests}) — the unread count on every admin response, the
 * list as an optional prop the header asks for only when the panel opens — so a
 * second endpoint returning the same rows would be a second place for the
 * permission filter to be got wrong.
 *
 * Two rules hold here, and both are ownership rather than UI:
 *
 *  1. Every query starts from `$request->user()->notifications()`. A staff
 *     member may only ever mark their own rows read, and scoping through the
 *     relation means "not yours" and "does not exist" are the same 404 — a
 *     bare `DatabaseNotification::findOrFail()` would answer 403 for a row that
 *     exists and 404 for one that does not, which tells an attacker which
 *     notification ids are real.
 *  2. Owning a row is not the same as being allowed to read it. Permissions can
 *     be narrowed after a notification lands, so the type's permission is
 *     re-checked on every click; {@see AdminNotificationData::TYPES} is the map.
 */
class NotificationController extends Controller
{
    /**
     * Open a notification: mark it read, then go to the record it is about.
     *
     * One request rather than a mark-read call racing a navigation, and the
     * destination is resolved here rather than taken from the row, so what the
     * link does is decided by this application every time it is followed.
     *
     * A GET that writes is unusual, and it is the right shape here: the write
     * is "I have seen my own notification", it is idempotent, and making it a
     * real link is what lets a staff member middle-click an order out of the
     * bell into a new tab.
     */
    public function show(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var DatabaseNotification $row */
        $row = $user->notifications()->whereKey($notification)->firstOrFail();

        abort_unless(AdminNotificationData::viewableBy($row, $user), 403);

        $row->markAsRead();

        // The subject can be gone — an order deleted, a payment purged — and a
        // dead link is a worse answer than the dashboard.
        return redirect()->to(
            AdminNotificationData::destinationFor($row) ?? route('admin.dashboard'),
        );
    }

    /**
     * Clear the badge.
     *
     * Deliberately only the types this viewer may see. Marking a row read is
     * saying "I have dealt with this", and a staff member cannot have dealt
     * with something the bell never showed them — if their permissions widen
     * later, those rows should still be waiting.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()
            ->whereIn('type', AdminNotificationData::visibleTypesFor($user))
            ->update(['read_at' => now()]);

        return back();
    }
}
