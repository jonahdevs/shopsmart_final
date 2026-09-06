{{--
    The printable receipt.

    Every figure comes from OrderData / OrderTotalsData — the same objects the
    order page renders — so the paper and the screen cannot disagree. Nothing
    here reads the catalog or does arithmetic: money arrives preformatted from
    money() on the server, exactly as it does for Vue.

    Styling is inline and self-contained. Browsershot renders this HTML in a
    headless Chrome that has no access to the Vite build, so a class from the
    storefront stylesheet would silently render as nothing.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt :number', ['number' => $order->orderNumber]) }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #16233a;
        }

        .sheet { padding: 28px 32px; }

        .masthead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0a57eb;
            padding-bottom: 14px;
        }

        .store-name { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; margin: 0; }
        .muted { color: #64748b; }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 9px;
            font-weight: 700;
            color: #0a57eb;
            margin: 0 0 4px;
        }

        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 16px; }

        .panels { display: flex; gap: 24px; margin: 22px 0; }
        .panel { flex: 1; }
        .panel h3 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .panel p { margin: 0; }

        table { width: 100%; border-collapse: collapse; }

        .lines th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 4px;
        }

        .lines td { padding: 8px 4px; border-bottom: 1px solid #e6ebf2; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }

        .totals { width: 46%; margin-left: auto; margin-top: 14px; }
        .totals td { padding: 4px 4px; }
        .totals tr.grand td {
            border-top: 2px solid #16233a;
            font-weight: 700;
            font-size: 13px;
            padding-top: 8px;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eaf0fe;
            color: #0a57eb;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .note { margin-top: 18px; padding: 10px 12px; background: #f4f7fc; border-radius: 8px; }

        footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #e6ebf2;
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="masthead">
        <div>
            <p class="eyebrow">{{ __('Receipt') }}</p>
            <h1 class="store-name">{{ $business->legal_name !== '' ? $business->legal_name : $storeName }}</h1>
            <p class="muted">{{ $business->address }}</p>
            <p class="muted">{{ $business->contact_email }} &middot; {{ $business->contact_phone }}</p>
            @if ($business->registration_number !== '')
                <p class="muted">{{ __('Reg. no.') }} {{ $business->registration_number }}</p>
            @endif
        </div>
        <div class="doc-title">
            <h2>{{ $order->orderNumber }}</h2>
            <p class="muted">{{ \Illuminate\Support\Carbon::parse($order->placedAt)->format('j M Y, H:i') }}</p>
            <p><span class="badge">{{ $order->statusLabel }}</span></p>
            <p><span class="badge">{{ $order->paymentStatusLabel }}</span></p>
        </div>
    </div>

    <div class="panels">
        <div class="panel">
            <h3>{{ __('Billed to') }}</h3>
            <p>{{ $order->customerName }}</p>
            <p class="muted">{{ $order->customerEmail }}</p>
            @if ($order->customerPhone)
                <p class="muted">{{ $order->customerPhone }}</p>
            @endif
        </div>
        <div class="panel">
            <h3>{{ $order->shippingAddress ? __('Delivering to') : __('Collection') }}</h3>
            @if ($order->shippingAddress)
                <p>{{ $order->shippingAddress->fullName }}</p>
                <p class="muted">{{ $order->shippingAddress->summary }}</p>
                @if ($order->shippingAddress->phone)
                    <p class="muted">{{ $order->shippingAddress->phone }}</p>
                @endif
            @else
                <p class="muted">{{ __('This order is being collected in store.') }}</p>
            @endif
        </div>
        <div class="panel">
            <h3>{{ __('Payment') }}</h3>
            <p>{{ $order->paymentMethod ?? __('Not recorded') }}</p>
            @if ($order->paidAt)
                <p class="muted">{{ __('Paid :date', ['date' => \Illuminate\Support\Carbon::parse($order->paidAt)->format('j M Y, H:i')]) }}</p>
            @endif
        </div>
    </div>

    <table class="lines">
        <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th class="center">{{ __('Qty') }}</th>
            <th class="num">{{ __('Unit') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->lines as $line)
            <tr>
                <td>
                    <strong>{{ $line->name }}</strong>
                    @if ($line->optionLabel)
                        <br><span class="muted">{{ $line->optionLabel }}</span>
                    @endif
                    @if ($line->sku)
                        <br><span class="muted">{{ __('SKU') }} {{ $line->sku }}</span>
                    @endif
                </td>
                <td class="center">{{ $line->quantity }}</td>
                <td class="num">{{ $line->unitPriceFormatted }}</td>
                <td class="num">{{ $line->totalFormatted }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('Subtotal') }}</td>
            <td class="num">{{ $order->totals->subtotalFormatted }}</td>
        </tr>
        @if ($order->totals->discountCents > 0)
            <tr>
                <td>{{ __('Discount') }}@if ($order->totals->couponCode) ({{ $order->totals->couponCode }})@endif</td>
                <td class="num">-{{ $order->totals->discountFormatted }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('Delivery') }}</td>
            <td class="num">{{ $order->totals->shippingIsFree ? __('Free') : $order->totals->shippingFormatted }}</td>
        </tr>
        <tr>
            <td>{{ $order->totals->taxLabel }}</td>
            <td class="num">{{ $order->totals->taxFormatted }}</td>
        </tr>
        <tr class="grand">
            <td>{{ __('Total') }}</td>
            <td class="num">{{ $order->totals->totalFormatted }}</td>
        </tr>
    </table>

    @if ($order->customerNote)
        <div class="note">
            <strong>{{ __('Your note') }}</strong><br>
            {{ $order->customerNote }}
        </div>
    @endif

    <footer>
        @if ($business->tax_pin !== '')
            <p>{{ __('PIN') }} {{ $business->tax_pin }}</p>
        @endif
        <p>{{ __('Generated :date. Keep this receipt for your records.', ['date' => now()->format('j M Y')]) }}</p>
    </footer>
</div>
</body>
</html>
