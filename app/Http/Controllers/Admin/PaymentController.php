<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsLikeQueries;
use App\Data\AdminPaymentRowData;
use App\Data\PaginationData;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentIndexRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Paystack\PaystackPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Collection attempts, for reconciliation.
 *
 * Read-only on purpose. A payment row is the record of what a gateway did, and
 * staff editing it would destroy the only account of that. Settlement happens
 * through {@see PaystackPaymentService} and
 * {@see Order::markPaid()} — never from this screen.
 *
 * The gateway `payload` is deliberately never sent to the client. It is
 * encrypted at rest because it carries the payer's name, phone and masked
 * instrument; `failure_reason` is the part of it staff actually need, and
 * {@see AdminPaymentRowData} exposes that and nothing else.
 */
class PaymentController extends Controller
{
    use BuildsLikeQueries;

    private const PER_PAGE = 25;

    public function index(PaymentIndexRequest $request): Response
    {
        $sort = $request->validated('sort') ?? 'created_at';
        $direction = $request->validated('direction') ?? 'desc';

        $payments = Payment::query()
            ->with('order:id,order_number')
            ->tap(fn (Builder $query) => $this->applyFilters($query, $request))
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/payments/Index', [
            'payments' => array_values(array_map(
                fn (Payment $payment): AdminPaymentRowData => AdminPaymentRowData::fromModel($payment),
                $payments->items(),
            )),
            'pagination' => PaginationData::fromPaginator($payments),
            'filters' => [
                'search' => $request->validated('search'),
                'status' => $request->validated('status'),
                'gateway' => $request->validated('gateway'),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => PaymentStatus::options(),
            'gateways' => $this->gateways(),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $payment->load('order:id,order_number');

        return Inertia::render('admin/payments/Show', [
            'payment' => AdminPaymentRowData::fromModel($payment),
        ]);
    }

    /**
     * The gateways that have actually been used, for the filter dropdown.
     *
     * Read off the rows rather than from a fixed list: a store that has only
     * ever taken Paystack should not be offered a filter that can only ever
     * return nothing.
     *
     * @return list<string>
     */
    private function gateways(): array
    {
        return array_values(array_map(
            static fn (mixed $gateway): string => (string) $gateway,
            Payment::query()->distinct()->orderBy('gateway')->pluck('gateway')->all(),
        ));
    }

    /**
     * @param  Builder<Payment>  $query
     */
    private function applyFilters(Builder $query, PaymentIndexRequest $request): void
    {
        $search = $request->validated('search');

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->containsPattern(trim($search));

            $query->where(function (Builder $match) use ($pattern): void {
                $match
                    ->whereRaw($this->likeExpression('reference'), [$pattern])
                    ->orWhereRaw($this->likeExpression('gateway_reference'), [$pattern])
                    ->orWhereHas('order', fn (Builder $order) => $order->whereRaw($this->likeExpression('order_number'), [$pattern]));
            });
        }

        $query
            ->when($request->validated('status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($request->validated('gateway'), fn (Builder $q, string $gateway) => $q->where('gateway', $gateway));
    }
}
