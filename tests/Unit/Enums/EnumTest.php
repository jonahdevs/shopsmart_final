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

test('the order lifecycle runs forwards and cancels only before delivery', function () {
    expect(OrderStatus::Pending->allowedTransitions())
        ->toBe([OrderStatus::Processing, OrderStatus::Cancelled])
        ->and(OrderStatus::Processing->allowedTransitions())
        ->toBe([OrderStatus::OutForDelivery, OrderStatus::Completed, OrderStatus::Cancelled])
        ->and(OrderStatus::OutForDelivery->allowedTransitions())
        ->toBe([OrderStatus::Completed, OrderStatus::Cancelled])
        // Only a delivered order can be refunded: money never collected cannot
        // be given back.
        ->and(OrderStatus::Completed->allowedTransitions())
        ->toBe([OrderStatus::Refunded])
        ->and(OrderStatus::Cancelled->allowedTransitions())->toBe([])
        ->and(OrderStatus::Refunded->allowedTransitions())->toBe([]);
});

test('an order may always be re-saved with the status it already holds', function () {
    // Staying put is not a move, so it is not in allowedTransitions() — but the
    // picker renders the current status as its selected option, and submitting
    // that untouched must not be an error.
    foreach (OrderStatus::cases() as $case) {
        expect($case->canTransitionTo($case))->toBeTrue()
            ->and($case->allowedTransitions())->not->toContain($case);
    }
});

test('a settled order cannot be dragged back into the workflow', function () {
    expect(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Pending))->toBeFalse()
        ->and(OrderStatus::Refunded->canTransitionTo(OrderStatus::Processing))->toBeFalse()
        ->and(OrderStatus::Completed->canTransitionTo(OrderStatus::Processing))->toBeFalse()
        ->and(OrderStatus::Completed->canTransitionTo(OrderStatus::Refunded))->toBeTrue();
});

test('a status is final exactly when it has nowhere left to go', function () {
    expect(OrderStatus::Cancelled->isFinal())->toBeTrue()
        ->and(OrderStatus::Refunded->isFinal())->toBeTrue()
        // Delivered is deliberately not final — it is the one state a refund
        // can start from.
        ->and(OrderStatus::Completed->isFinal())->toBeFalse()
        ->and(OrderStatus::Pending->isFinal())->toBeFalse();
});

it('only uses valid shadcn badge variants', function (string $enum) {
    if (! method_exists($enum, 'badgeVariant')) {
        expect(true)->toBeTrue();

        return;
    }

    foreach ($enum::cases() as $case) {
        expect($case->badgeVariant())->toBeIn(['default', 'secondary', 'destructive', 'outline']);
    }
})->with('domain enums');
