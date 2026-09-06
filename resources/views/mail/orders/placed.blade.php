{{--
    Confirms the commitment, not the money: the order is created unpaid, so this
    tells the shopper what they ordered and where it is going, then points at
    the order page — which doubles as the place to pay if they still owe.
--}}
<x-mail::message>
# {{ __('Thanks, :name.', ['name' => $order->customer_name]) }}

{{ __('We have your order :number. Here is what you asked for.', ['number' => $order->order_number]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Total') }} |
|:-----------------|:---------------:|------------------:|
@foreach ($order->items as $item)
| {{ $item->name }}{{ $item->option_label ? ' — '.$item->option_label : '' }} | {{ $item->quantity }} | {{ money($item->total_cents) }} |
@endforeach
| **{{ __('Order total') }}** | | **{{ money($order->total_cents) }}** |
</x-mail::table>

@if ($order->shipping_line1 !== null)
**{{ __('Delivering to') }}**
{{ trim($order->shipping_first_name.' '.$order->shipping_last_name) }}
{{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif

{{ $order->shipping_city }}@if ($order->shipping_county), {{ $order->shipping_county }}@endif
@else
{{ __('You chose to collect this order in store. We will let you know when it is ready.') }}
@endif

@if ($order->awaitsPayment())
{{ __('This order is not paid for yet. You can settle it from the order page whenever you are ready.') }}
@endif

<x-mail::button :url="$url">
{{ __('View your order') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
