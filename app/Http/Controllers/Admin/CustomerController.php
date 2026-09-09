<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminCustomerDetailData;
use App\Data\AdminCustomerRowData;
use App\Data\AdminCustomerStatsData;
use App\Data\AdminDashboardStatsData;
use App\Data\PaginationData;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerIndexRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people who shop here.
 *
 * A customer is a user holding no role at all — the same boundary
 * {@see User::isCustomer()} draws — so staff accounts never appear in this
 * table and a staff id in the URL is a 404 rather than a profile.
 *
 * This section is read-only apart from one field. There is no password reset,
 * no impersonation and no email change here on purpose: each of those turns a
 * support tool into a way into somebody's account. Correcting a misspelled
 * display name is the whole of what `customers.manage` buys, and even that is
 * separated from `customers.view` so a Support role can read without writing.
 *
 * What the pages do not show is as deliberate as what they do — see
 * {@see AdminCustomerDetailData} for the list, of which the encrypted payment
 * payload is the one that matters most.
 */
class CustomerController extends Controller
{
    use BuildsLikeQueries;

    /** Rows per page in the customers table. */
    private const PER_PAGE = 25;

    /** The trailing window the registration tile is measured over. */
    private const WINDOW_DAYS = 30;

    public function index(CustomerIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';

        $customers = $this->customers()
            ->withCount('orders')
            // Two aggregates rather than a loaded relation: this page shows 25
            // customers, and hydrating every order each of them ever placed to
            // display a total would be the one query here that grows with the
            // store's whole history.
            ->withSum(
                ['orders as lifetime_spent_cents' => fn (Builder $paid) => $paid
                    ->where('payment_status', PaymentStatus::Success)],
                'total_cents',
            )
            ->withMax('orders as last_order_at', 'placed_at')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/customers/Index', [
            'customers' => array_values(array_map(
                fn (User $customer): AdminCustomerRowData => AdminCustomerRowData::fromModel($customer),
                $customers->items(),
            )),
            'pagination' => PaginationData::fromPaginator($customers),
            'filters' => [
                'search' => $request->validated('search'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'stats' => $this->stats(),
        ]);
    }

    public function show(User $customer): Response
    {
        $this->abortUnlessCustomer($customer);

        return Inertia::render('admin/customers/Show', [
            'detail' => AdminCustomerDetailData::fromModel($customer),
        ]);
    }

    /**
     * Correct the customer's display name.
     *
     * `update()` rather than `forceFill()` so mass-assignment protection still
     * applies, and the request validates one field — an email arriving in this
     * payload is dropped rather than honoured.
     */
    public function update(UpdateCustomerRequest $request, User $customer): RedirectResponse
    {
        $this->abortUnlessCustomer($customer);

        $customer->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return back();
    }

    /**
     * Staff are not customers, and an admin URL must not say whether a given id
     * belongs to a colleague — 404 answers both.
     */
    private function abortUnlessCustomer(User $customer): void
    {
        abort_unless($customer->isCustomer(), 404);
    }

    /**
     * The four tiles above the table.
     *
     * Two queries, not four. The first is one pass over `users` for the head
     * count and both sides of the registration delta; the second is one pass
     * over `orders` for who has actually paid and what they paid in total. They
     * cannot be a single statement: joining customers to their orders would
     * multiply the user rows and quietly overstate every count taken from them.
     *
     * The order-side pass qualifies its customers through the relation rather
     * than reading `orders.user_id` on its own, so a colleague who shops here on
     * a staff account stays outside a figure the customers page is describing.
     *
     * The live window closes on `<=` and the previous one on `<`. Both matter:
     * somebody who registered this very second belongs to today rather than to
     * nowhere, and the two windows still meet without an account that lands on
     * the seam being counted twice.
     */
    private function stats(): AdminCustomerStatsData
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);
        $previousStart = $now->copy()->subDays(self::WINDOW_DAYS * 2);

        $registrations = $this->customers()
            ->selectRaw(
                <<<'SQL'
                    COUNT(*) as total,
                    SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as joined_now,
                    SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as joined_before
                    SQL,
                [$windowStart, $now, $previousStart, $windowStart],
            )
            ->first();

        $spend = Order::query()
            ->where('payment_status', PaymentStatus::Success)
            ->whereHas('user', fn (Builder $customer) => $customer->whereDoesntHave('roles'))
            ->selectRaw('COUNT(DISTINCT user_id) as payers, COALESCE(SUM(total_cents), 0) as revenue')
            ->first();

        $customerCount = (int) ($registrations?->getAttribute('total') ?? 0);
        $newCustomers = (int) ($registrations?->getAttribute('joined_now') ?? 0);
        $previousCustomers = (int) ($registrations?->getAttribute('joined_before') ?? 0);

        $payingCustomers = (int) ($spend?->getAttribute('payers') ?? 0);
        $revenue = (int) ($spend?->getAttribute('revenue') ?? 0);

        // Integer division on purpose: this is money, and a fractional cent has
        // nowhere to go. A store whose customers have never paid for anything is
        // an ordinary state, not a division by zero.
        $averageSpend = $payingCustomers > 0 ? intdiv($revenue, $payingCustomers) : 0;

        return new AdminCustomerStatsData(
            customerCount: $customerCount,
            newCustomerCount: $newCustomers,
            // The overview's helper rather than a second one: what a delta means
            // — and that a first period shows none — is settled in one place for
            // every tile in the back office.
            newCustomerChangePercent: AdminDashboardStatsData::changePercent($newCustomers, $previousCustomers),
            payingCustomerCount: $payingCustomers,
            payingCustomerSharePercent: $customerCount > 0
                ? round(($payingCustomers / $customerCount) * 100, 1)
                : null,
            averageSpendCents: $averageSpend,
            averageSpendFormatted: money($averageSpend),
            periodLabel: __('Last :days days', ['days' => self::WINDOW_DAYS]),
        );
    }

    /**
     * @return Builder<User>
     */
    private function customers(): Builder
    {
        return User::query()->whereDoesntHave('roles');
    }

    /**
     * Narrow the table by the filter bar.
     *
     * Orders carry a frozen `customer_email` that survives account deletion, so
     * searching is deliberately confined to the `users` table: matching against
     * order snapshots would surface people who have already closed their
     * accounts, which is the opposite of what deleting one means.
     *
     * @param  Builder<User>  $query
     */
    private function applyFilters(Builder $query, CustomerIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (! is_string($search) || trim($search) === '') {
            return;
        }

        $pattern = $this->containsPattern(trim($search));

        $query->where(function (Builder $match) use ($pattern): void {
            $match
                ->whereRaw($this->likeExpression('name'), [$pattern])
                ->orWhereRaw($this->likeExpression('email'), [$pattern]);
        });
    }
}
