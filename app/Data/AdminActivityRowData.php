<?php

namespace App\Data;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry in the audit trail.
 *
 * Two things are deliberately *not* here.
 *
 * The `properties` column is never read. Model changes live in
 * `attribute_changes`, which holds exactly the attributes the model chose to log;
 * `properties` is a free-form bag any future call site can put anything into,
 * and a screen that dumps it would leak whatever that turns out to be.
 *
 * And the values themselves are gated. The activity log records staff actions
 * against customer records, so it is personal data in its own right:
 * `activity.view` says you may see *that* an order moved, not that you may read
 * the order. When the viewer lacks the permission that governs the subject
 * ({@see SUBJECT_PERMISSIONS}) the attribute names still show — an auditor needs
 * to know something changed — but `from` and `to` come through null and the row
 * says why. A subject type nobody has taught this class about is treated as
 * secret rather than public.
 */
#[TypeScript]
class AdminActivityRowData extends Data
{
    /**
     * The permission that governs each subject's *values*.
     *
     * @var array<class-string, string>
     */
    public const SUBJECT_PERMISSIONS = [
        Order::class => 'orders.view',
        Payment::class => 'payments.view',
    ];

    /**
     * @param  list<AdminActivityChangeData>  $changes
     */
    public function __construct(
        public int $id,
        public string $logName,
        public string $description,
        public ?string $event,
        /** The subject's class basename — "Order", "Payment" — never the FQCN. */
        public ?string $subjectType,
        public ?int $subjectId,
        /** How staff refer to the subject: an order number, a payment reference. */
        public ?string $subjectLabel,
        public ?string $causerName,
        public ?int $causerId,
        public array $changes,
        /** True when the viewer may see what changed but not the values. */
        public bool $valuesHidden,
        public string $createdAt,
    ) {}

    public static function fromModel(Activity $activity, bool $maySeeValues): self
    {
        $subject = $activity->subject;

        return new self(
            id: $activity->getKey(),
            logName: $activity->log_name ?? (string) config('activitylog.default_log_name'),
            description: $activity->description,
            event: $activity->event,
            subjectType: $activity->subject_type === null ? null : class_basename($activity->subject_type),
            subjectId: $activity->subject_id === null ? null : (int) $activity->subject_id,
            subjectLabel: self::labelFor($subject),
            causerName: self::causerName($activity->causer),
            causerId: $activity->causer_id === null ? null : (int) $activity->causer_id,
            changes: self::changes($activity, $maySeeValues),
            valuesHidden: ! $maySeeValues,
            createdAt: $activity->created_at?->toIso8601String() ?? '',
        );
    }

    /**
     * The attributes this entry recorded as having moved.
     *
     * @return list<AdminActivityChangeData>
     */
    private static function changes(Activity $activity, bool $maySeeValues): array
    {
        $recorded = $activity->attribute_changes?->toArray() ?? [];

        $new = is_array($recorded['attributes'] ?? null) ? $recorded['attributes'] : [];
        $old = is_array($recorded['old'] ?? null) ? $recorded['old'] : [];

        /** @var list<string> $attributes */
        $attributes = array_values(array_unique([
            ...array_keys($new),
            ...array_keys($old),
        ]));

        sort($attributes);

        return array_map(
            static fn (string $attribute): AdminActivityChangeData => new AdminActivityChangeData(
                attribute: $attribute,
                label: ucfirst(str_replace('_', ' ', preg_replace('/_cents$/', '', $attribute) ?? $attribute)),
                from: $maySeeValues ? self::readable($attribute, $old[$attribute] ?? null) : null,
                to: $maySeeValues ? self::readable($attribute, $new[$attribute] ?? null) : null,
            ),
            $attributes,
        );
    }

    /**
     * A logged value as a person reads it.
     *
     * Integer cents are formatted as money for the same reason every other
     * screen does it — "150000" is not a price anyone recognises. Anything that
     * is not a scalar is described rather than dumped, because a nested array in
     * an audit row is a payload, and payloads are what this class refuses to show.
     */
    private static function readable(string $attribute, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_int($value) && str_ends_with($attribute, '_cents')) {
            return money($value);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '—';
    }

    private static function labelFor(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Order => $subject->order_number,
            $subject instanceof Payment => $subject->reference,
            default => null,
        };
    }

    /**
     * Who did it. "System" covers a queued job, a webhook or an artisan command
     * — anything the application did without a signed-in person behind it.
     */
    private static function causerName(?Model $causer): ?string
    {
        if ($causer === null) {
            return null;
        }

        $name = $causer->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
