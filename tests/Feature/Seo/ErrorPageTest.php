<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * What a shopper sees when something goes wrong.
 *
 * The status code matters as much as the page: a 404 that answers 200 is
 * invisible to a crawler and to monitoring, so every case here asserts both the
 * component and the code it was served with.
 */
beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.testing.ensure_pages_exist', false);

    // The handler stands aside in debug mode so a developer keeps the stack
    // trace; these assertions are about what production serves.
    config()->set('app.debug', false);
});

test('a dead link renders the branded page and still answers 404', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(
            fn ($page) => $page
                ->component('errors/Error')
                ->where('status', 404)
        );
});

test('a withdrawn product is a 404 rather than a blank page', function () {
    $draft = Product::factory()->draft()->create();

    $this->get(route('product.show', $draft->slug))
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('errors/Error'));
});

test('a customer refused the admin panel gets the branded 403', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden()
        ->assertInertia(
            fn ($page) => $page
                ->component('errors/Error')
                ->where('status', 403)
        );
});

test('an order that is not yours stays a 404, not a 403', function () {
    // Telling a stranger the order exists but is not theirs is more than they
    // need to know, so the storefront answers 404 — the error page must not
    // quietly turn that into a 403.
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order->order_number))
        ->assertNotFound();
});

test('an api route answers json rather than an inertia page', function () {
    $this->getJson('/api/no-such-endpoint')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('a json request anywhere is answered as json', function () {
    $this->getJson('/no-such-page')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('debug mode keeps the developer stack trace instead of the friendly page', function () {
    config()->set('app.debug', true);

    // A friendly page that hides the trace would be a step backwards locally.
    $response = $this->get('/no-such-page')->assertNotFound();

    expect($response->getContent())->not->toContain('errors/Error');
});

test('a staff member refused a section they lack permission for sees the page too', function () {
    $this->seed(PermissionSeeder::class);

    $support = User::factory()->create();
    $support->assignRole('Support');

    $this->actingAs($support)
        ->get(route('admin.roles.index'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('errors/Error'));
});
