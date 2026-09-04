<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products and everything that hangs directly off one.
 *
 * Two product types are implemented: `simple` (one SKU) and `variable`
 * (attribute-driven variants, each with its own SKU and stock). The `type`
 * discriminator also carries `grouped` and `bundled` so those can be added
 * later without a data migration, but no tables back them yet.
 *
 * All money is stored as integer cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identity. `sku` is null for variable products — the variants
            // carry the real SKUs.
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('model_number')->nullable();

            $table->string('type')->default('simple');

            // `scheduled` publishes once published_at passes; see the
            // Product::published() scope.
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('technical_specification')->nullable();

            // Base price in cents. Nullable so a product can be
            // price-on-application. Variants may override.
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedBigInteger('cost_price')->nullable();

            $table->boolean('is_taxable')->default(true);
            $table->foreignId('tax_class_id')->nullable()->constrained('tax_classes')->nullOnDelete();

            // A virtual product (a service, an installation) never ships.
            $table->boolean('is_virtual')->default(false);
            $table->boolean('requires_shipping')->default(true);

            // Measurements are stored in the unit snapshotted on the product at
            // creation time, so changing the store-wide units later never
            // reinterprets existing values.
            $table->decimal('weight', 8, 3)->nullable();
            $table->string('weight_unit', 8)->nullable();
            $table->decimal('length', 8, 3)->nullable();
            $table->decimal('width', 8, 3)->nullable();
            $table->decimal('height', 8, 3)->nullable();
            $table->string('dimension_unit', 8)->nullable();

            // stock_quantity NULL means stock is not tracked for this product.
            // Variable products track per variant.
            $table->string('stock_status')->default('in_stock');
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->unsignedInteger('min_order_quantity')->nullable();

            $table->string('visibility')->default('visible');

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url', 500)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Catalog listings filter on status + visibility and sort by
            // sort_order; the storefront never reads a soft-deleted row.
            $table->index(['status', 'visibility']);
            $table->index('stock_status');
            $table->index('sort_order');
            $table->index('published_at');
            $table->index('deleted_at');
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });

        // Curated product-to-product recommendations. One typed table for
        // upsells, cross-sells, accessories and spare parts: they share the
        // same shape (a directed, ordered pointer) and differ only by `type`.
        Schema::create('product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type');
            // Accessory-only: required accessories are pre-checked on the
            // "complete your purchase" prompt, and default_quantity seeds its
            // stepper (one oven needs 12 trays, another needs 6).
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('default_quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'linked_product_id', 'type']);
            $table->index(['product_id', 'type']);
        });

        // Which attributes a product uses, and whether each one generates
        // variants or is display-only spec data.
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->json('values')->nullable();
            $table->boolean('is_variation_attribute')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->unique();
            $table->string('barcode')->nullable();

            // Null price inherits the parent product's. `sale_price` is the
            // discounted price and `price` the struck-through original --
            // deliberately the same convention as `products`, unlike the
            // reference app which inverted the two on variants only.
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedBigInteger('cost_price')->nullable();

            $table->string('stock_status')->default('in_stock');
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->boolean('allow_backorder')->default(false);

            $table->decimal('weight', 8, 3)->nullable();
            $table->decimal('length', 8, 3)->nullable();
            $table->decimal('width', 8, 3)->nullable();
            $table->decimal('height', 8, 3)->nullable();

            // Falls back to the product's short_description when null.
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
            $table->index('deleted_at');
        });

        // The attribute values that define a variant: "Red / XL" is two rows.
        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'attribute_value_id'], 'variant_attribute_value_primary');
            $table->index('attribute_value_id');
        });

        // Added after product_variants exists so the constraint can be created.
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('default_variant_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('product_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_variant_id');
        });

        Schema::dropIfExists('attribute_value_product_variant');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_links');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};
