<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, House, Search } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { catalog, home } from '@/routes';

const { status } = defineProps<{
    status: number;
}>();

/**
 * One component for every handled status.
 *
 * Separate pages would mean five near-identical files drifting apart; the only
 * thing that actually differs is the sentence, and what to offer next. The copy
 * says what happened and what the shopper can do, and never blames them.
 */
const messages: Record<number, { title: string; body: string }> = {
    403: {
        title: 'This page is not yours to see',
        body: 'You are signed in, but this page belongs to someone else or to staff. If you think that is wrong, get in touch.',
    },
    404: {
        title: 'We could not find that page',
        body: 'The link may be old, or the product may have been withdrawn. Everything still on sale is in the shop.',
    },
    429: {
        title: 'That was a little too quick',
        body: 'Too many requests arrived from your connection in a short time. Wait a moment and try again.',
    },
    500: {
        title: 'Something went wrong at our end',
        body: 'This one is ours, not yours. The problem has been logged and someone will look at it.',
    },
    503: {
        title: 'The shop is briefly closed',
        body: 'We are making a change and will be back shortly. Nothing in your cart has been lost.',
    },
};

const fallback = {
    title: 'Something went wrong',
    body: 'That request could not be completed. Try again, or head back to the shop.',
};

const message = computed(() => messages[status] ?? fallback);

/**
 * `history.length > 1` is the closest a page can get to asking whether there is
 * anywhere to go back to. It is not exact — a long-lived tab accumulates
 * history — but it correctly hides the button for the case that matters: a
 * pasted link opened in a fresh tab.
 */
const canGoBack = typeof window !== 'undefined' && window.history.length > 1;

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <div
        class="container flex min-h-[60vh] flex-col items-center justify-center py-16 text-center"
    >
        <Head :title="message.title" />

        <p
            class="text-electric font-display text-6xl font-extrabold tracking-[-0.04em] sm:text-7xl"
            aria-hidden="true"
        >
            {{ status }}
        </p>

        <h1
            class="font-display text-ink mt-4 text-2xl font-extrabold tracking-[-0.03em] sm:text-3xl"
        >
            {{ message.title }}
        </h1>

        <p class="text-muted-foreground mt-3 max-w-prose text-sm sm:text-base">
            {{ message.body }}
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Button as-child>
                <Link :href="home()">
                    <House class="size-4" aria-hidden="true" />
                    Back to the shop
                </Link>
            </Button>

            <Button variant="outline" as-child>
                <Link :href="catalog()">
                    <Search class="size-4" aria-hidden="true" />
                    Browse everything
                </Link>
            </Button>

            <!--
              A real button, not a Link: this goes back through the browser's
              own history, which Inertia has no route for. Hidden when there is
              no history to return to, so it cannot be a button that does
              nothing — someone who opened this URL directly has nowhere back.
            -->
            <Button
                v-if="canGoBack"
                variant="ghost"
                type="button"
                @click="goBack"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Go back
            </Button>
        </div>
    </div>
</template>
