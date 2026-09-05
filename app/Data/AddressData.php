<?php

namespace App\Data;

use App\Models\Address;
use App\Models\Order;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A delivery destination, from the address book or frozen onto an order.
 *
 * {@see fromOrder()} reads the snapshot columns rather than the address
 * relation, so an order still shows where it actually went after the address
 * behind it has been edited or deleted. It returns null for a collection order,
 * which has no destination at all.
 *
 * `summary` is the address on one line, for the places that only have room for
 * one — the picker's radio label, the order card.
 */
#[TypeScript]
class AddressData extends Data
{
    public function __construct(
        public ?int $id,
        public ?string $label,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $phone,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $county,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $deliveryNotes,
        public bool $isDefault,
        public string $summary,
    ) {}

    public static function fromModel(Address $address): self
    {
        return new self(
            id: $address->getKey(),
            label: $address->label,
            firstName: $address->first_name,
            lastName: $address->last_name,
            fullName: $address->fullName(),
            phone: $address->phone,
            line1: $address->line1,
            line2: $address->line2,
            city: $address->city,
            county: $address->county,
            postalCode: $address->postal_code,
            countryCode: $address->country_code,
            deliveryNotes: $address->delivery_notes,
            isDefault: $address->is_default,
            summary: self::summarise($address->line1, $address->line2, $address->city, $address->county),
        );
    }

    /** The destination as the order recorded it, or null for a collection order. */
    public static function fromOrder(Order $order): ?self
    {
        if ($order->shipping_line1 === null) {
            return null;
        }

        return new self(
            id: $order->shipping_address_id,
            label: null,
            firstName: (string) $order->shipping_first_name,
            lastName: (string) $order->shipping_last_name,
            fullName: trim($order->shipping_first_name.' '.$order->shipping_last_name),
            phone: $order->shipping_phone,
            line1: $order->shipping_line1,
            line2: $order->shipping_line2,
            city: (string) $order->shipping_city,
            county: $order->shipping_county,
            postalCode: $order->shipping_postal_code,
            countryCode: (string) $order->shipping_country_code,
            deliveryNotes: null,
            isDefault: false,
            summary: self::summarise(
                $order->shipping_line1,
                $order->shipping_line2,
                (string) $order->shipping_city,
                $order->shipping_county,
            ),
        );
    }

    private static function summarise(string $line1, ?string $line2, string $city, ?string $county): string
    {
        return implode(', ', array_filter([$line1, $line2, $city, $county]));
    }
}
