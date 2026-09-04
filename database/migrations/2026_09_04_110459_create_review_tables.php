<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer reviews plus the two view-tracking tables that feed
 * "recently viewed" and "customers also viewed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Null once the reviewer deletes their account: the review stays,
            // attributed to the snapshotted author_name.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status')->default('pending');
            $table->boolean('verified_purchase')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Product pages and the admin moderation queue both filter on status.
            $table->index(['product_id', 'status']);
            $table->index('status');
        });

        // One row per authenticated user per product, refreshed on each view.
        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'viewed_at']);
        });

        // Append-only analytics log covering guests too, throttled per session
        // in the application layer. Feeds the "customers also viewed" rail.
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->timestamp('viewed_at');

            $table->index('session_id');
            $table->index(['product_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_views');
        Schema::dropIfExists('recently_viewed');
        Schema::dropIfExists('reviews');
    }
};
