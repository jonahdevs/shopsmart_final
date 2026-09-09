<?php

namespace App\Data;

use App\Notifications\Staff\NewOrderPlaced;
use App\Notifications\Staff\PaymentReceived;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row in the admin notification bell.
 *
 * Storage is Laravel's own `notifications` table, read through the `Notifiable`
 * trait — ordering and read state are the table's `created_at` and `read_at`,
 * and there is no second mechanism tracking either.
 *
 * The row carries no destination URL. The bell links every entry at this
 * application's own notification route and the server resolves where that goes
 * on the click, so a value stored months ago can never become the thing that
 * decides where a staff member is sent.
 *
 * The bell is gated the same way the audit trail is, and for the same reason:
 * a notification about an order is a fact about an order, so the permission
 * that guards the record guards what is said about it. {@see TYPES} is the map,
 * and it is deliberately a whitelist — a kind nobody has taught this class
 * about is invisible rather than public, so a notification added later cannot
 * leak by being forgotten here.
 *
 * The filter is applied when the bell is READ, never only when it is written.
 * A staff member whose permissions are narrowed keeps the rows already in their
 * inbox, and re-checking on read is what stops those rows still being legible.
 *
 * `title` and `body` are frozen at write time by the notification's
 * `toDatabase()`. That is deliberate: a formatted total keeps the currency it
 * was taken in, and opening the bell does not load fifteen orders to describe
 * them. The counterpart is that this class never touches a subject either — the
 * click-through resolves one, for the single row that was clicked.
 */
#[TypeScript]
class AdminNotificationData extends Data
{
    /**
     * Every staff-facing kind the bell knows, keyed by the `type` each
     * notification writes into its own payload.
     *
     * One table rather than three, because these four facts are the same
     * decision seen from four sides and they must not drift apart:
     *
     *  - `class`      — what the table's own `type` column holds, which is what
     *                   the listing query filters on. Indexed, so the count on
     *                   every admin response stays a key read.
     *  - `permission` — the permission on the admin screen this kind links to,
     *                   so the bell can never offer a destination `can:` would
     *                   then refuse.
     *  - `icon`/`tone`— presentation, read from the map rather than the stored
     *                   payload: what an order alert looks like is a decision
     *                   about the design system, not a fact about one row, and
     *                   baking it into every row would freeze it there.
     *
     * @var array<string, array{class: class-string, permission: string, icon: string, tone: string}>
     */
    public const TYPES = [
        'new_order' => [
            'class' => NewOrderPlaced::class,
            'permission' => 'orders.view',
            'icon' => 'order',
            'tone' => 'info',
        ],
        'payment_received' => [
            'class' => PaymentReceived::class,
            'permission' => 'payments.view',
            'icon' => 'payment',
            'tone' => 'success',
        ],
    ];

    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        /** A key the bell maps to a lucide glyph — never a component name. */
        public string $icon,
        /** An `AdminTone` name from resources/js/components/admin/tones.ts. */
        public string $tone,
        public bool $isUnread,
        public string $createdAt,
        public string $createdAtForHumans,
    ) {}

    /**
     * The notification classes this viewer may see at all.
     *
     * Returned as class names so the caller can put them straight into a
     * `whereIn('type', …)` against the table's indexed column — the filter
     * belongs in the query, not in a post-fetch array_filter that would make
     * "the latest fifteen" mean "up to fifteen of the latest fifteen".
     *
     * @return list<class-string>
     */
    public static function visibleTypesFor(?Authorizable $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        return array_values(array_map(
            static fn (array $type): string => $type['class'],
            array_filter(
                self::TYPES,
                static fn (array $type): bool => $viewer->can($type['permission']),
            ),
        ));
    }

    /**
     * Whether this viewer may see one particular notification.
     *
     * The click-through's gate. Separate from {@see visibleTypesFor()} because
     * that answers a query and this answers a request, and a request may arrive
     * for a row the listing never offered.
     */
    public static function viewableBy(DatabaseNotification $notification, ?Authorizable $viewer): bool
    {
        $type = self::typeOf($notification);

        return $type !== null && $viewer !== null && $viewer->can($type['permission']);
    }

    public static function fromNotification(DatabaseNotification $notification): self
    {
        $type = self::typeOf($notification);

        return new self(
            id: (string) $notification->getKey(),
            title: self::text($notification, 'title'),
            body: self::text($notification, 'body'),
            icon: $type['icon'] ?? 'order',
            tone: $type['tone'] ?? 'neutral',
            isUnread: $notification->read_at === null,
            createdAt: $notification->created_at?->toIso8601String() ?? '',
            createdAtForHumans: $notification->created_at?->diffForHumans() ?? '',
        );
    }

    /**
     * The admin screen a notification is about, or null when the record it
     * described has since been deleted.
     *
     * Lives beside the permission map on purpose. "What is this about" and "who
     * may read it" are the same question asked twice, and a second copy of the
     * answer on a controller is how one of them ends up pointing somewhere the
     * other never authorised.
     */
    public static function destinationFor(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;

        return match (self::key($notification)) {
            'new_order' => self::orderUrl(self::text($notification, 'order_number')),
            'payment_received' => self::paymentUrl($data['payment_id'] ?? null),
            default => null,
        };
    }

    /**
     * @return array{class: class-string, permission: string, icon: string, tone: string}|null
     */
    private static function typeOf(DatabaseNotification $notification): ?array
    {
        $key = self::key($notification);

        return $key === null ? null : (self::TYPES[$key] ?? null);
    }

    /**
     * The kind a row says it is.
     *
     * Read from the payload, then checked against the table's own `type`
     * column. Both are written by us in the same insert, so a mismatch means a
     * row was tampered with or hand-edited, and the answer to that is "this is
     * not a kind I know" rather than a best guess.
     */
    private static function key(DatabaseNotification $notification): ?string
    {
        $key = $notification->data['type'] ?? null;

        if (! is_string($key) || ! array_key_exists($key, self::TYPES)) {
            return null;
        }

        return self::TYPES[$key]['class'] === $notification->type ? $key : null;
    }

    private static function orderUrl(string $orderNumber): ?string
    {
        return $orderNumber === '' ? null : route('admin.orders.show', $orderNumber);
    }

    private static function paymentUrl(mixed $paymentId): ?string
    {
        return is_int($paymentId) || (is_string($paymentId) && $paymentId !== '')
            ? route('admin.payments.show', $paymentId)
            : null;
    }

    /**
     * A string off the stored payload.
     *
     * `data` is a JSON column, so anything in it is whatever was written the
     * day it was written. Non-strings are dropped rather than cast, which keeps
     * a payload written by an older version of a notification class from
     * rendering as "Array" in the bell.
     */
    private static function text(DatabaseNotification $notification, string $key): string
    {
        $value = $notification->data[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
