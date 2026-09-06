{{--
    One message per real fulfilment transition. The previous status is passed in
    because the row only carries the new one by the time the queue runs it, and
    "completed" reads very differently after "out for delivery" than after
    "cancelled".
--}}
<x-mail::message>
# {{ __('Order :number is now :status', ['number' => $order->order_number, 'status' => $order->status->label()]) }}

{{ __('It was :previous when we last wrote to you.', ['previous' => $previousStatus->label()]) }}

@switch($order->status)
@case(\App\Enums\OrderStatus::OutForDelivery)
{{ __('Your order has left us and is on its way. Keep your phone nearby — the rider will call on :phone.', ['phone' => $order->customer_phone ?? $order->shipping_phone]) }}
@break
@case(\App\Enums\OrderStatus::Completed)
{{ __('Your order has been delivered. If anything is not right, reply to this email and we will sort it out.') }}
@break
@case(\App\Enums\OrderStatus::Cancelled)
{{ __('This order has been cancelled. Nothing further will be delivered, and any payment taken will be returned.') }}
@break
@case(\App\Enums\OrderStatus::Refunded)
{{ __('This order has been refunded. The money is on its way back to where it came from.') }}
@break
@default
{{ __('We will let you know as soon as it moves again.') }}
@endswitch

<x-mail::button :url="$url">
{{ __('View your order') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
