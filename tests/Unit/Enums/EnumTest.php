<?php

use App\Enums\AttributeType;
use App\Enums\CategorySection;
use App\Enums\CategoryStatus;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductLinkType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVisibility;
use App\Enums\ReviewStatus;
use App\Enums\StockStatus;
use Tests\TestCase;

uses(TestCase::class);

dataset('domain enums', [
    AttributeType::class,
    CategorySection::class,
    CategoryStatus::class,
    CouponType::class,
    OrderStatus::class,
    PaymentStatus::class,
    ProductLinkType::class,
    ProductStatus::class,
    ProductType::class,
    ProductVisibility::class,
    ReviewStatus::class,
    StockStatus::class,
]);

it('gives every case a non-empty label', function (string $enum) {
    expect(count($enum::cases()))->toBeGreaterThan(0);

    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->toMatch('/\S/');
    }
})->with('domain enums');

it('returns one option per case', function (string $enum) {
    $options = $enum::options();

    expect(count($options))->toBe(count($enum::cases()));

    foreach ($enum::cases() as $index => $case) {
        expect($options[$index])->toBe([
            'value' => $case->value,
            'label' => $case->label(),
        ]);
    }
})->with('domain enums');

it('only uses valid shadcn badge variants', function (string $enum) {
    if (! method_exists($enum, 'badgeVariant')) {
        expect(true)->toBeTrue();

        return;
    }

    foreach ($enum::cases() as $case) {
        expect($case->badgeVariant())->toBeIn(['default', 'secondary', 'destructive', 'outline']);
    }
})->with('domain enums');
