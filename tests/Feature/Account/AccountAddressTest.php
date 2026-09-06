<?php

use App\Models\Address;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The address book as the account area uses it: reading the list, editing an
 * entry, and moving the default.
 *
 * The invariant worth protecting is that exactly one address per customer
 * carries the default flag, and that no request can reach an address belonging
 * to somebody else.
 */
beforeEach(function () {
    // Asserts page props, not markup, so it must not depend on a JS build.
    $this->withoutVite();

    // The phase 6 page components are built by another agent from the props
    // asserted here.
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->customer = User::factory()->create();
});

/** @return array<string, string> */
function addressPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Amina',
        'last_name' => 'Wanjiru',
        'phone' => '0712345678',
        'line1' => 'Nyati Road 14',
        'city' => 'Nairobi',
        'country_code' => 'KE',
        ...$overrides,
    ];
}

test('the addresses page lists this customer book, default first', function () {
    $plain = Address::factory()->create(['user_id' => $this->customer->id]);
    $default = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);

    Address::factory()->create(['user_id' => User::factory()]);

    $this->actingAs($this->customer)
        ->get(route('account.addresses'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('account/Addresses')
            ->has('addresses', 2)
            ->where('addresses.0.id', $default->id)
            ->where('addresses.1.id', $plain->id)
            ->has('breadcrumbs', 2));
});

test('a customer can edit their own address', function () {
    $address = Address::factory()->create(['user_id' => $this->customer->id, 'city' => 'Nairobi']);

    $this->actingAs($this->customer)
        ->from(route('account.addresses'))
        ->patch(route('addresses.update', $address), addressPayload(['city' => 'Mombasa']))
        ->assertRedirect(route('account.addresses'))
        ->assertSessionHasNoErrors();

    expect($address->fresh()->city)->toBe('Mombasa');
});

test('editing an address rejects an incomplete one', function () {
    $address = Address::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->patch(route('addresses.update', $address), addressPayload(['line1' => '', 'city' => '']))
        ->assertSessionHasErrors(['line1', 'city']);
});

test('another customer address is not found rather than forbidden', function () {
    $address = Address::factory()->create(['user_id' => User::factory()]);

    $this->actingAs($this->customer)
        ->patch(route('addresses.update', $address), addressPayload())
        ->assertNotFound();

    $this->actingAs($this->customer)
        ->patch(route('addresses.default', $address))
        ->assertNotFound();

    expect($address->fresh()->is_default)->toBeFalse();
});

test('promoting an address demotes the one that held the default', function () {
    $wasDefault = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);
    $promoted = Address::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->from(route('account.addresses'))
        ->patch(route('addresses.default', $promoted))
        ->assertRedirect(route('account.addresses'));

    expect($promoted->fresh()->is_default)->toBeTrue()
        ->and($wasDefault->fresh()->is_default)->toBeFalse();
});

test('promoting an address leaves another customer default alone', function () {
    $stranger = User::factory()->create();
    $strangersDefault = Address::factory()->isDefault()->create(['user_id' => $stranger->id]);
    $mine = Address::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)->patch(route('addresses.default', $mine));

    expect($strangersDefault->fresh()->is_default)->toBeTrue();
});

test('editing an address into the default demotes the others', function () {
    $wasDefault = Address::factory()->isDefault()->create(['user_id' => $this->customer->id]);
    $edited = Address::factory()->create(['user_id' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->patch(route('addresses.update', $edited), addressPayload(['is_default' => true]));

    expect($edited->fresh()->is_default)->toBeTrue()
        ->and($wasDefault->fresh()->is_default)->toBeFalse();
});

test('a guest cannot reach the address book', function () {
    $address = Address::factory()->create(['user_id' => $this->customer->id]);

    $this->get(route('account.addresses'))->assertRedirect(route('login'));
    $this->patch(route('addresses.update', $address))->assertRedirect(route('login'));
    $this->patch(route('addresses.default', $address))->assertRedirect(route('login'));
});
