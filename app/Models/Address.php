<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An entry in a customer's address book.
 *
 * Nothing here is authoritative for a placed order: checkout copies every field
 * it needs onto the order at placement, so editing or deleting an address after
 * the fact leaves the destination that was actually shipped to intact.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $county
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $delivery_notes
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id', 'label', 'first_name', 'last_name', 'phone', 'line1', 'line2',
    'city', 'county', 'postal_code', 'country_code', 'delivery_notes', 'is_default',
])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // ==================================================
    // RELATIONSHIPS
    // ==================================================

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================================================
    // SCOPES
    // ==================================================

    /**
     * The customer's default address first, then newest.
     *
     * @param  Builder<Address>  $query
     */
    #[Scope]
    protected function inPickOrder(Builder $query): void
    {
        $query->orderByDesc('is_default')->orderByDesc('id');
    }

    // ==================================================
    // HELPERS
    // ==================================================

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * The address as the order snapshot columns want it, keyed to match.
     *
     * @return array<string, string|null>
     */
    public function toOrderSnapshot(): array
    {
        return [
            'shipping_first_name' => $this->first_name,
            'shipping_last_name' => $this->last_name,
            'shipping_phone' => $this->phone,
            'shipping_line1' => $this->line1,
            'shipping_line2' => $this->line2,
            'shipping_city' => $this->city,
            'shipping_county' => $this->county,
            'shipping_postal_code' => $this->postal_code,
            'shipping_country_code' => $this->country_code,
        ];
    }
}
