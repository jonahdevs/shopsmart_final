<?php

use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

/**
 * The header autocomplete. It runs on every keystroke, so the two-character
 * floor and the visibility rules matter more here than the ranking does.
 */
test('search suggest needs at least two characters', function () {
    $this->getJson(route('search.suggest', ['q' => 'k']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');

    $this->getJson(route('search.suggest'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('q');
});

test('search suggest returns matching products, categories and brands', function () {
    $product = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);
    Product::factory()->published()->create(['name' => 'Toaster']);

    $category = Category::factory()->create(['name' => 'Kettles']);
    Category::factory()->create(['name' => 'Toasters']);

    $brand = Brand::factory()->create(['name' => 'Kettleworks']);
    Product::factory()->published()->create(['brand_id' => $brand->id]);
    Brand::factory()->create(['name' => 'Toastworks']);

    $response = $this->getJson(route('search.suggest', ['q' => 'kettle']))->assertOk();

    expect($response->json('query'))->toBe('kettle')
        ->and($response->json('products.*.id'))->toContain($product->id)
        ->and($response->json('categories.*.id'))->toBe([$category->id])
        ->and($response->json('brands.*.id'))->toBe([$brand->id]);
});

test('search suggest hides products that are not published or not search visible', function () {
    Product::factory()->draft()->create(['name' => 'Kettle Draft']);
    Product::factory()->published()->hidden()->create(['name' => 'Kettle Hidden']);
    Product::factory()->published()->create(['name' => 'Kettle Catalog', 'visibility' => ProductVisibility::Catalog]);
    $visible = Product::factory()->published()->create(['name' => 'Kettle Deluxe']);

    $response = $this->getJson(route('search.suggest', ['q' => 'kettle']))->assertOk();

    expect($response->json('products.*.id'))->toBe([$visible->id]);
});
