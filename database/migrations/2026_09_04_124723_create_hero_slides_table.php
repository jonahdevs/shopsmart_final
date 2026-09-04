<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The home-page hero carousel. The reference app hardcoded these slides as a
 * PHP array in the Blade view; here they are rows an admin can edit, reorder
 * and schedule.
 *
 * Slide artwork is not a column: the desktop and mobile images live in
 * medialibrary collections on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('headline');
            $table->string('subheadline')->nullable();
            // A slide may be purely visual, so both halves of the call to
            // action are optional — but they are only rendered together.
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            // Where the text block sits over the art: left | center | right.
            $table->string('alignment')->default('left');
            // dark | light — light type for slides with dark artwork.
            $table->string('text_theme')->default('dark');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Optional campaign window. A null bound means "no bound", so a
            // slide with both null is live for as long as it is active.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            // Serves the storefront's only read: live slides in display order.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
