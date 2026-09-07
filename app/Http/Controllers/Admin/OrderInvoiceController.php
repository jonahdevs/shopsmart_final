<?php

namespace App\Http\Controllers\Admin;

use App\Data\OrderData;
use App\Http\Controllers\Account\OrderReceiptController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Settings\BusinessSettings;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * The staff copy of an order document.
 *
 * It renders `pdf.receipt` — the same view, from the same {@see OrderData} — as
 * the customer's own download. Two near-identical templates would drift the
 * first time a total or a tax label changed on one of them, and a document that
 * disagrees with the copy the customer is holding is the worst kind of bug to
 * settle on the phone. Only the filename differs, so the two are still tellable
 * apart in a downloads folder.
 *
 * Nothing staff-only is added to the sheet. `staff_note` in particular stays
 * off it: it lives in a separate column from `customer_note` precisely so an
 * internal remark cannot end up on something the customer sees, and staff read
 * it on the order page.
 *
 * Note what is deliberately absent. {@see OrderReceiptController} guards itself
 * with `abort_unless($order->user_id === $request->user()?->getKey(), 404)`;
 * this one must not, because staff are not the customer and the order is never
 * theirs. Authorisation here is `can:orders.view` on the route. Do not "fix"
 * the missing ownership check back in — it would refuse every order in the shop.
 */
class OrderInvoiceController extends Controller
{
    public function __invoke(Order $order, BusinessSettings $business): PdfBuilder
    {
        $order->load('items');

        return Pdf::view('pdf.receipt', [
            'order' => OrderData::fromModel($order),
            'business' => $business,
            'storeName' => config('app.name'),
        ])
            ->format(Format::A4)
            ->name("invoice-{$order->order_number}.pdf")
            ->download();
    }
}
