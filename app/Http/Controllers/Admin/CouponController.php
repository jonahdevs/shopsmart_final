<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminCouponDetailData;
use App\Data\AdminCouponRowData;
use App\Data\PaginationData;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponIndexRequest;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Discount codes, end to end.
 *
 * One permission — `marketing.manage` — covers the section: a coupon is a
 * commercial decision, and there is no useful role that may read the terms of a
 * discount but not set them.
 *
 * Two rules hold this controller together.
 *
 * Money arrives as whole KES and leaves as integer cents, converted once in
 * {@see StoreCouponRequest::couponAttributes()}. Nothing here multiplies by a
 * hundred.
 *
 * `used_count` is never written from here. It belongs to
 * {@see Order::recordCouponUse()}, which increments it only when the
 * `(coupon_id, order_id)` unique index actually admitted a new `coupon_uses`
 * row — so a replayed payment confirmation cannot burn a limited coupon's
 * budget twice. An admin path that set the counter directly would break that
 * pairing, and the counter is what enforces the usage limit.
 */
class CouponController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the coupons table. */
    private const PER_PAGE = 25;

    public function index(CouponIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';

        $coupons = Coupon::query()
            ->withCount('uses')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/coupons/Index', [
            'coupons' => array_values(array_map(
                fn (Coupon $coupon): AdminCouponRowData => AdminCouponRowData::fromModel($coupon),
                $coupons->items(),
            )),
            'pagination' => PaginationData::fromPaginator($coupons),
            'filters' => [
                'search' => $request->validated('search'),
                'type' => $request->validated('type'),
                'state' => $request->validated('state'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'typeOptions' => CouponType::options(),
            'stateOptions' => self::stateOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/Create', [
            'typeOptions' => CouponType::options(),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = Coupon::query()->create($request->couponAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Coupon :code created.', ['code' => $coupon->code]),
        ]);

        return to_route('admin.coupons.show', $coupon);
    }

    public function show(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/Show', [
            'detail' => AdminCouponDetailData::fromModel($coupon),
        ]);
    }

    public function edit(Coupon $coupon): Response
    {
        $coupon->loadCount('uses');

        return Inertia::render('admin/coupons/Edit', [
            'coupon' => AdminCouponRowData::fromModel($coupon),
            'typeOptions' => CouponType::options(),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->couponAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon updated.')]);

        return to_route('admin.coupons.show', $coupon);
    }

    /**
     * Retire a coupon.
     *
     * A code that has been redeemed is never deleted: `coupon_uses` cascades on
     * delete, so removing the row would take the redemption history with it and
     * leave the orders it discounted pointing at nothing. Those are switched
     * off instead, which stops any further use and keeps the record. Only a
     * code nobody ever used can actually go.
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->uses()->exists()) {
            $coupon->update(['is_active' => false]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('This code has been redeemed, so it was deactivated rather than deleted.'),
            ]);

            return to_route('admin.coupons.show', $coupon);
        }

        $coupon->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coupon deleted.')]);

        return to_route('admin.coupons.index');
    }

    /**
     * The lifecycle states the filter bar offers.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function stateOptions(): array
    {
        return [
            ['value' => 'live', 'label' => __('Live')],
            ['value' => 'scheduled', 'label' => __('Scheduled')],
            ['value' => 'expired', 'label' => __('Expired')],
            ['value' => 'inactive', 'label' => __('Switched off')],
        ];
    }

    /**
     * Narrow the table by the filter bar.
     *
     * "Live" is the same three conditions {@see Coupon::active()} composes, so
     * a code the filter calls live is one the checkout would accept.
     *
     * @param  Builder<Coupon>  $query
     */
    private function applyFilters(Builder $query, CouponIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('code'), [$pattern])
                    ->orWhereRaw($this->likeExpression('description'), [$pattern]);
            });
        }

        $query->when(
            $request->validated('type'),
            fn (Builder $q, string $type) => $q->where('type', $type),
        );

        match ($request->validated('state')) {
            'live' => $query->active(),
            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', now()),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }
}
