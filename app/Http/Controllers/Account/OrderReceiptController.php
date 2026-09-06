<?php

namespace App\Http\Controllers\Account;

use App\Data\OrderData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\OrderController;
use App\Models\Order;
use App\Settings\BusinessSettings;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * The order as a document the customer can keep.
 *
 * Built from the same {@see OrderData} the order page renders, so the figures
 * on the paper and the figures on the screen come from one computation — a
 * receipt that disagrees with the page it was printed from is worse than no
 * receipt at all.
 *
 * Ownership is a 404 rather than a 403, matching
 * {@see OrderController}: a stranger learning that
 * an order number exists is already more than they need to know.
 */
class OrderReceiptController extends Controller
{
    public function __invoke(Request $request, Order $order, BusinessSettings $business): PdfBuilder
    {
        abort_unless($order->user_id === $request->user()?->getKey(), 404);

        $order->load('items');

        return Pdf::view('pdf.receipt', [
            'order' => OrderData::fromModel($order),
            'business' => $business,
            'storeName' => config('app.name'),
        ])
            ->format(Format::A4)
            ->name("receipt-{$order->order_number}.pdf")
            ->download();
    }
}
