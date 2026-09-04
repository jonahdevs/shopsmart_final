<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The taxonomy a product hangs off: tax classes, attributes and their values,
 * brands, categories, and the curated placements that drive storefront nav.
 */
return new class extends Migration
{
    public function up(): void
    {
        // VAT bands. Products either carry a class or fall back to the store
        // default in TaxSettings.
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('rate', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Attributes drive variant generation (Size, Colour) and the product
        // spec table. `type` decides how values render: plain select, colour
        // swatch, or button group.
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('select');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->string('slug');
            $table->text('description')->nullable();
            // Hex swatch, only meaningful when the parent attribute is type=color.
            $table->string('color_code')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
            $table->index(['attribute_id', 'is_active']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Self-referencing tree. Depth is not constrained; the storefront walks
        // the subtree when filtering a category listing.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable();
            // Inline SVG for the nav/category tiles, avoiding an image request
            // for what is usually a single-colour glyph.
            $table->text('icon_svg')->nullable();
            $table->string('status')->default('draft');
            $table->integer('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        // Which categories surface in the navbar, on the home page, and in the
        // footer, and in what order. A category can appear in each location once.
        Schema::create('category_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['category_id', 'location']);
            $table->index(['location', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_placements');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('tax_classes');
    }
};
