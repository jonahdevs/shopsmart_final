<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A staff member moving an order's fulfilment status.
 *
 * The lifecycle guard lives here and NOT in {@see Order::changeStatus()}. That
 * method is the mechanical writer: it moves the column, wins the race and sends
 * the one email. It is deliberately free of policy, because the things that
 * write a status are not all people — the order factory and the demo seeder
 * construct orders in whatever state a fixture needs, and Paystack's
 * confirmation drives one straight to `processing` through
 * {@see Order::markPaid()}. A lifecycle rule inside the writer would make those
 * impossible to express.
 *
 * Which moves a *person* may make is a different question, and it only has to
 * hold at the door people come through. This is that door.
 */
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(OrderStatus::class),
                $this->mustBeALegalMove(),
            ],
        ];
    }

    /**
     * Refuse a move the order's current status does not allow.
     *
     * Reported against `status` rather than as a 403: the staff member is not
     * forbidden from touching orders, they picked a destination this order
     * cannot reach — usually because someone else moved it while the page was
     * open. The message names both ends so the form says what actually happened.
     */
    private function mustBeALegalMove(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $order = $this->route('order');
            $target = is_string($value) ? OrderStatus::tryFrom($value) : null;

            if (! $order instanceof Order || $target === null) {
                return;
            }

            if ($order->status->canTransitionTo($target)) {
                return;
            }

            $fail(__('An order that is :current cannot be moved to :target.', [
                'current' => mb_strtolower($order->status->label()),
                'target' => mb_strtolower($target->label()),
            ]));
        };
    }
}
