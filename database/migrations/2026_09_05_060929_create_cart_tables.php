<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The durable half of the storefront session.
 *
 * Guests keep their cart, wishlist and compare tray in the session and touch
 * none of these tables. A signed-in shopper works from the same session copy,
 * which is mirrored here on every mutation so it survives a new session or a
 * second device, and is merged back on login.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One cart per customer. Guests have no identity to hang a durable cart
        // on, so there is no guest token column — an abandoned cart we could
        // never email about is not worth a row.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Stamped on every mutation, for the abandoned-cart reminder that
            // Phase 7 adds and for pruning stale rows.
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('last_activity_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            // A product or variant that is hard-deleted takes its cart lines
            // with it. Both models soft-delete, so the storefront's own
            // pruning (a soft-deleted product drops out of the rendered cart)
            // is what shoppers actually hit; this is the backstop.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            // The price the shopper saw when the line was opened, in cents, so
            // a later catalog price change cannot silently rewrite the cart.
            // Checkout re-prices against the catalog; this is what the storefront
            // compares against to say "the price of this item has changed".
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();

            // One line per cart/product/variant triple. MySQL treats NULLs as
            // distinct in a unique index, so this does not constrain
            // variant-less lines; the write path resolves lines by the same
            // triple, which is what actually keeps them unique.
            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_line_unique');
        });

        // Wishlist and compare share a shape — an ordered set of products per
        // customer — so they share a table and differ only by `list`.
        Schema::create('saved_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('list', 16);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'list', 'product_id'], 'saved_products_entry_unique');
            // Serves the only read: one customer's list, in saved order.
            $table->index(['user_id', 'list', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_products');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
