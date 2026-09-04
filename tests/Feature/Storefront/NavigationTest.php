<?php

use App\Enums\CategorySection;
use App\Models\Category;
use App\Models\CategoryPlacement;
use Inertia\Testing\AssertableInertia;

/**
 * The header stripe is shared on every storefront response from
 * HandleInertiaRequests, and it is curated through CategoryPlacement rather
 * than "all top-level categories" — so it has to honour both halves of a
 * placement: where it points and whether it is live.
 */
test('the navbar lists active navbar placements in their sort order', function () {
    $second = Category::factory()->create(['name' => 'Second']);
    $first = Category::factory()->create(['name' => 'First']);

    CategoryPlacement::factory()->forCategory($second)->location(CategorySection::Navbar)->sortOrder(2)->create();
    CategoryPlacement::factory()->forCategory($first)->location(CategorySection::Navbar)->sortOrder(1)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('storefront.navCategories', 2)
            ->where('storefront.navCategories.0.slug', $first->slug)
            ->where('storefront.navCategories.1.slug', $second->slug));
});

test('a placement that is not active is kept out of the navbar', function () {
    $live = Category::factory()->create();
    $hidden = Category::factory()->create();

    CategoryPlacement::factory()->forCategory($live)->location(CategorySection::Navbar)->create();
    CategoryPlacement::factory()->forCategory($hidden)->location(CategorySection::Navbar)->draft()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('storefront.navCategories', 1)
            ->where('storefront.navCategories.0.slug', $live->slug));
});

test('placements pinned elsewhere never reach the navbar', function () {
    $category = Category::factory()->create();

    CategoryPlacement::factory()->forCategory($category)->location(CategorySection::Footer)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('storefront.navCategories', 0));
});
