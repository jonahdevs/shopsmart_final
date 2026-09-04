<?php

use App\Enums\AttributeType;
use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\CategoryPlacement;
use App\Models\Product;
use App\Models\TaxClass;

test('a category exposes its parent and its children', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();

    expect($child->parent->is($root))->toBeTrue()
        ->and($root->children->pluck('id')->all())->toBe([$child->id]);
});

test('descendantIds walks the whole subtree in a single query', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();
    $grandchild = Category::factory()->child($child)->create();
    $greatGrandchild = Category::factory()->child($grandchild)->create();

    $unrelated = Category::factory()->create();
    Category::factory()->child($unrelated)->create();

    DB::enableQueryLog();

    $ids = $root->descendantIds();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($ids)->toEqualCanonicalizing([
            $root->id,
            $child->id,
            $grandchild->id,
            $greatGrandchild->id,
        ]);
});

test('descendantIds returns only the category itself when it is a leaf', function () {
    $leaf = Category::factory()->create();

    expect($leaf->descendantIds())->toBe([$leaf->id]);
});

test('categories and products share an ordered pivot', function () {
    $category = Category::factory()->create();
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    $category->products()->attach([
        $first->id => ['sort_order' => 2],
        $second->id => ['sort_order' => 1],
    ]);

    $ordered = $category->products()->orderBy('category_product.sort_order')->get();

    expect($ordered->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($ordered->first()->pivot->sort_order)->toBe(1);
});

test('attribute values come back in sort order', function () {
    $attribute = Attribute::factory()->create();

    $last = AttributeValue::factory()->forAttribute($attribute)->sortOrder(30)->create();
    $first = AttributeValue::factory()->forAttribute($attribute)->sortOrder(10)->create();
    $middle = AttributeValue::factory()->forAttribute($attribute)->sortOrder(20)->create();

    expect($attribute->values->pluck('id')->all())
        ->toBe([$first->id, $middle->id, $last->id]);
});

test('placements are scoped and ordered by storefront location', function () {
    $navbarSecond = CategoryPlacement::factory()->location(CategorySection::Navbar)->sortOrder(2)->create();
    $navbarFirst = CategoryPlacement::factory()->location(CategorySection::Navbar)->sortOrder(1)->create();
    CategoryPlacement::factory()->location(CategorySection::Footer)->create();

    $navbar = CategoryPlacement::forLocation(CategorySection::Navbar)->get();

    expect($navbar->pluck('id')->all())->toBe([$navbarFirst->id, $navbarSecond->id]);
});

test('the active scopes filter on their status column', function () {
    $active = Category::factory()->active()->create();
    Category::factory()->draft()->create();

    $liveTaxClass = TaxClass::factory()->create();
    TaxClass::factory()->inactive()->create();

    expect(Category::active()->pluck('id')->all())->toBe([$active->id])
        ->and(TaxClass::active()->pluck('id')->all())->toBe([$liveTaxClass->id]);
});

test('enum casts round-trip through the database', function () {
    $category = Category::factory()->create(['status' => CategoryStatus::Archived]);
    $attribute = Attribute::factory()->ofType(AttributeType::Color)->create();
    $placement = CategoryPlacement::factory()
        ->forCategory($category)
        ->location(CategorySection::HomePageFeatured)
        ->draft()
        ->create();

    expect($category->fresh()->status)->toBe(CategoryStatus::Archived)
        ->and($attribute->fresh()->type)->toBe(AttributeType::Color)
        ->and($placement->fresh()->location)->toBe(CategorySection::HomePageFeatured)
        ->and($placement->fresh()->status)->toBe(CategoryStatus::Draft);

    expect(DB::table('categories')->where('id', $category->id)->value('status'))->toBe('archived')
        ->and(DB::table('category_placements')->where('id', $placement->id)->value('location'))
        ->toBe('homepage_featured');
});
