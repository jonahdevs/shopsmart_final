<script setup lang="ts">
import { Mail } from '@lucide/vue';

/**
 * The newsletter card that closes every storefront page.
 *
 * There is no subscriber backend in this build — it was deliberately cut from
 * scope — so the card is deliberately inert: no `<form>`, no action, no route,
 * and both controls carry a real `disabled` attribute rather than only looking
 * unavailable. The caption says so out loud so a shopper is not left typing
 * into a field that will never accept them. Wire it to a controller and this
 * becomes an Inertia `<Form v-bind="...form()">` in place.
 */
</script>

<template>
    <section class="container pt-16 pb-14">
        <div
            class="bg-panel flex flex-col gap-7 rounded-lg px-6 py-8 sm:px-10 lg:flex-row lg:items-center lg:justify-between lg:gap-10"
        >
            <div class="flex items-center gap-5">
                <span
                    class="bg-electric flex size-12 shrink-0 items-center justify-center rounded-lg text-white"
                >
                    <Mail class="size-6" aria-hidden="true" />
                </span>

                <div>
                    <h2 class="font-display text-lg font-bold text-white">
                        Be the first to know
                    </h2>
                    <p class="mt-1 text-sm text-white/55">
                        Get the latest deals, new arrivals and exclusive offers.
                    </p>
                </div>
            </div>

            <div class="w-full lg:max-w-md">
                <div
                    class="flex items-center overflow-hidden rounded-lg bg-white opacity-60"
                >
                    <label for="newsletter-email" class="sr-only">
                        Email address
                    </label>
                    <input
                        id="newsletter-email"
                        type="email"
                        autocomplete="email"
                        placeholder="Enter your email address"
                        disabled
                        aria-describedby="newsletter-status"
                        class="text-ink placeholder:text-muted-foreground min-w-0 flex-1 cursor-not-allowed bg-transparent px-4 py-3 text-sm"
                    />
                    <button
                        type="button"
                        disabled
                        class="bg-electric shrink-0 cursor-not-allowed self-stretch px-6 text-sm font-bold text-white"
                    >
                        Subscribe
                    </button>
                </div>

                <p id="newsletter-status" class="mt-2 text-xs text-white/45">
                    Email signups open soon — nothing to enter just yet.
                </p>
            </div>
        </div>
    </section>
</template>
