<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * The downloadable receipt.
 *
 * The render itself is faked. Producing a real PDF boots a headless Chrome,
 * which costs seconds per run and fails outright on a machine with no browser
 * installed — neither belongs in a test suite. What is asserted here is
 * everything up to and including the render call: who may ask for one, which
 * view is used, and that the figures reaching that view are the order's own.
 */
beforeEach(function () {
    $this->customer = User::factory()->create();

    $this->order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'customer_name' => 'Amina Wanjiru',
        'subtotal_cents' => 300_000,
        'discount_cents' => 0,
        'shipping_cents' => 30_000,
        'tax_cents' => 45_862,
        'total_cents' => 330_000,
    ]);

    OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'name' => 'Ridgeline Drill',
        'quantity' => 2,
        'unit_price_cents' => 150_000,
        'subtotal_cents' => 300_000,
        'total_cents' => 300_000,
    ]);
});

test('the owner gets a receipt built from their order', function () {
    $pdf = Pdf::fake();

    $this->actingAs($this->customer)
        ->get(route('orders.receipt', $this->order))
        ->assertOk();

    // `assertRespondedWithPdf` rather than `assertViewIs` / `assertSee`: those
    // two read the fake's saved-and-generated log, which a PDF returned as a
    // response never joins. The HTML is rendered here instead.
    $pdf->assertRespondedWithPdf(function (PdfBuilder $built): bool {
        $html = $built->getHtml();

        return $built->viewName === 'pdf.receipt'
            && str_contains($html, $this->order->order_number)
            && str_contains($html, 'Amina Wanjiru')
            && str_contains($html, 'Ridgeline Drill')
            && str_contains($html, money(330_000));
    });
});

test('the receipt is served as a named attachment', function () {
    $pdf = Pdf::fake();

    $this->actingAs($this->customer)->get(route('orders.receipt', $this->order));

    $pdf->assertRespondedWithPdf(
        fn (PdfBuilder $built): bool => $built->downloadName === "receipt-{$this->order->order_number}.pdf",
    );
});

test('another customer receipt is not found rather than forbidden', function () {
    Pdf::fake();

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('orders.receipt', $this->order))
        ->assertNotFound();
});

test('a guest is sent to sign in rather than handed a receipt', function () {
    $this->get(route('orders.receipt', $this->order))->assertRedirect(route('login'));
});
