<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the storefront needs to turn a cart into money owed.
 *
 * Two principles run through the whole schema. First, an order is a snapshot:
 * it copies the customer, the destination and every line off the catalog at
 * placement, so editing a product or deleting an address can never rewrite
 * history. Second, money is a signed bigInteger of cents — `int` tops out at
 * roughly KES 21 M, which a single order can clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Counters for human-facing document numbers. A row per series; the
        // value is the NEXT number to hand out. A rolled-back transaction burns
        // a number, which is the accepted trade for never issuing one twice.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The shopper's own label for the entry: "Home", "Office".
            $table->string('label')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('county')->nullable();
            $table->string('postal_code')->nullable();
            $table->char('country_code', 2)->default('KE');
            $table->text('delivery_notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // No soft deletes: orders snapshot every field they need, so a
            // deleted address costs a placed order nothing.
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Stored uppercase; lookups upcase the shopper's input.
            $table->string('code')->unique();
            $table->string('type');
            // Two explicit columns rather than one overloaded `value`, so
            // "is this cents or a percentage?" is never a runtime question
            // about `type`. Exactly one is set, per the type.
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedBigInteger('min_subtotal_cents')->default(0);
            // Ceiling on what a percentage coupon can take off.
            $table->unsignedBigInteger('max_discount_cents')->nullable();
            // Null means unlimited, on both counters.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            // Serves the only listing query: live coupons, soonest to lapse.
            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            // Nullable + nullOnDelete because deleting a customer must not take
            // the sales history with it. ProfileController::destroy hard-deletes
            // the user, so the snapshot below is the only surviving record of
            // who placed this.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();

            $table->char('currency', 3)->default('KES');
            // Snapshot of TaxSettings::$prices_include_tax at placement, so the
            // order page can label its VAT line correctly forever.
            $table->boolean('prices_include_tax');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('shipping_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot so deleting a coupon does not blank order history.
            $table->string('coupon_code')->nullable();

            $table->string('delivery_method');
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            // Snapshot of the destination. Nullable only because a pickup order
            // has none; every delivery order writes all of it at placement.
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_line1')->nullable();
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_county')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->char('shipping_country_code', 2)->nullable();

            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();

            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Stamped when stock is taken off this order, and checked before
            // taking it, so a replayed payment confirmation cannot deduct twice.
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamps();

            // "My orders, newest first" — the customer's order history.
            $table->index(['user_id', 'placed_at']);
            $table->index('status');
            $table->index('payment_status');
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // nullOnDelete, not cascade: a hard-deleted product must not erase
            // the line that was sold. Everything needed to render it is copied
            // into the columns below.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('option_label')->nullable();

            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_cents');
            // Pre-discount: unit price times quantity.
            $table->bigInteger('subtotal_cents');
            // This line's share of the order discount, allocated pro-rata.
            $table->bigInteger('discount_cents')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');

            // The whole priced line as it was rendered, for anything the columns
            // above do not carry: brand, image, slug.
            $table->json('product_snapshot');
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });

        Schema::create('coupon_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('discount_cents');
            $table->timestamps();

            // Redemption happens once, on payment confirmation. This makes a
            // second recording impossible in the database rather than merely
            // unlikely in the code: a replayed webhook cannot burn the budget
            // twice.
            $table->unique(['coupon_id', 'order_id']);
            // Backs the per-user usage limit count.
            $table->index(['coupon_id', 'user_id']);
        });

        // Created now, though nothing settles a row until phase 5 adds Paystack,
        // so the gateway ships without a migration of its own.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Ours, generated at placement and handed to the gateway. Unique
            // because it is the idempotency key for both the browser's verify
            // call and the asynchronous webhook.
            $table->string('reference')->unique();
            $table->string('gateway')->default('paystack');
            $table->string('status')->default('pending');
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('KES');
            // What the gateway settled through: card, mobile_money, bank.
            $table->string('channel')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('failure_reason')->nullable();
            // The verify/webhook response, kept whole for audit.
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('coupon_uses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('number_sequences');
    }
};
