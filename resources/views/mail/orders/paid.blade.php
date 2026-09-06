{{--
    The receipt. Sent once, from the branch of Order::markPaid() that actually
    settled the row, so a replayed webhook never produces a second one.
--}}
<x-mail::message>
# {{ __('Payment received') }}

{{ __('We have received :total for order :number. It is now being prepared.', [
    'total' => money($order->total_cents),
    'number' => $order->order_number,
]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Total') }} |
|:-----------------|:---------------:|------------------:|
@foreach ($order->items as $item)
| {{ $item->name }}{{ $item->option_label ? ' — '.$item->option_label : '' }} | {{ $item->quantity }} | {{ money($item->total_cents) }} |
@endforeach
</x-mail::table>

| | |
|:--|--:|
| {{ __('Subtotal') }} | {{ money($order->subtotal_cents) }} |
@if ($order->discount_cents > 0)
| {{ __('Discount') }}{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }} | -{{ money($order->discount_cents) }} |
@endif
| {{ __('Delivery') }} | {{ money($order->shipping_cents) }} |
| {{ $order->prices_include_tax ? __('VAT (incl.)') : __('VAT') }} | {{ money($order->tax_cents) }} |
| **{{ __('Total paid') }}** | **{{ money($order->total_cents) }}** |

<x-mail::button :url="$url">
{{ __('View your order') }}
</x-mail::button>

{{ __('A printable receipt is available here: :url', ['url' => $receiptUrl]) }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
