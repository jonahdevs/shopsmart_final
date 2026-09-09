<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per browsing session, written only for a visitor who granted
 * analytics consent.
 *
 * Deliberately not one row per page view. A page-view table grows with traffic
 * rather than with audience, and every question the dashboard asks of it —
 * how many people, on what, new or returning — is a question about sessions.
 * Counting sessions also keeps the personal data in here proportionate: one IP
 * address per visit rather than one per click.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();

            // The visitor's own pseudonymous id, held in a first-party cookie.
            // Not unique: a returning visitor gets a second row under the same
            // id, which is what makes "new vs returning" answerable at all.
            $table->uuid('tracking_id');

            $table->boolean('is_new')->default(true);
            $table->string('ip', 45)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamps();

            // Every read is "sessions inside a window", grouped by one of the
            // low-cardinality columns, so the window is what has to be indexed.
            $table->index('created_at');
            $table->index(['tracking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
