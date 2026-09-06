<?php

use App\Enums\ReviewAuthorFormat;
use App\Models\Product;
use App\Models\Review;
use App\Settings\ReviewSettings;
use Inertia\Testing\AssertableInertia;

/**
 * How much of a reviewer's name the storefront prints.
 *
 * `reviews.author_name` is a snapshot: it is written when the review is left
 * and it survives the reviewer deleting their account, so it is the one piece
 * of a departed customer that the store keeps publishing. The setting is
 * therefore a real privacy control rather than a cosmetic one, and it is
 * applied where the name crosses to the client — not in the template, which
 * would leave the full name sitting in the page payload.
 */
beforeEach(function () {
    $this->withoutVite();
});

function useAuthorFormat(ReviewAuthorFormat $format): void
{
    $settings = app(ReviewSettings::class);
    $settings->author_display_format = $format->value;
    $settings->save();
}

test('the abbreviated format shortens a surname to an initial', function (string $stored, string $shown) {
    expect(ReviewAuthorFormat::FirstNameAndInitial->apply($stored))->toBe($shown);
})->with([
    'two names' => ['Jane Wanjiru', 'Jane W.'],
    'three names' => ['Mary Jane Otieno', 'Mary O.'],
    'one name only' => ['Kamau', 'Kamau'],
    'untidy spacing' => ['  Jane   Wanjiru  ', 'Jane W.'],
    'empty' => ['', ''],
]);

test('the full format returns the stored name unchanged', function () {
    expect(ReviewAuthorFormat::FullName->apply('Jane Wanjiru'))->toBe('Jane Wanjiru');
});

test('a product page sends only the abbreviated name when that is the store setting', function () {
    useAuthorFormat(ReviewAuthorFormat::FirstNameAndInitial);

    $product = Product::factory()->published()->create();
    Review::factory()->approved()->for($product)->create(['author_name' => 'Jane Wanjiru']);

    $this->get(route('product.show', $product))
        ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps(
            fn (AssertableInertia $reload) => $reload->where('reviews.0.authorName', 'Jane W.'),
        ));
});

test('a product page sends the full name when the store asks for it', function () {
    useAuthorFormat(ReviewAuthorFormat::FullName);

    $product = Product::factory()->published()->create();
    Review::factory()->approved()->for($product)->create(['author_name' => 'Jane Wanjiru']);

    $this->get(route('product.show', $product))
        ->assertInertia(fn (AssertableInertia $page) => $page->loadDeferredProps(
            fn (AssertableInertia $reload) => $reload->where('reviews.0.authorName', 'Jane Wanjiru'),
        ));
});

test('an unrecognised stored format falls back to the abbreviated one', function () {
    $settings = app(ReviewSettings::class);
    $settings->author_display_format = 'something-else';

    expect($settings->authorFormat())->toBe(ReviewAuthorFormat::FirstNameAndInitial);
});
