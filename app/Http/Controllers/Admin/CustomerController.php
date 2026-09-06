<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminCustomerDetailData;
use App\Data\AdminCustomerRowData;
use App\Data\PaginationData;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerIndexRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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
