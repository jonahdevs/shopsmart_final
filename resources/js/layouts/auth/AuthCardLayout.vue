<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StoreWordmark from '@/components/storefront/StoreWordmark.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

/**
 * The auth screens, carried on a card.
 *
 * The form sits on a raised white card over a muted ground rather than
 * floating on the page, which is the same "content on cards, cards off the
 * page" language the storefront uses. `AuthSimpleLayout` is the flat
 * alternative; `AuthLayout` picks between them in one place.
 *
 * `Card` is stripped of its own vertical padding so the header and the content
 * own the gutters — otherwise the card's `py-6` stacks on top of theirs.
 */
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <!--
              The shop's own wordmark, not a framework logo. These pages are the
              first thing a customer sees after the storefront hands them off,
              so the mark has to be the same one they just clicked away from.
            -->
            <Link
                :href="home()"
                class="focus-visible:outline-electric self-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4"
                aria-label="ShopSmart home"
            >
                <StoreWordmark tone="onLight" />
            </Link>

            <Card class="rounded-xl py-0">
                <CardHeader class="px-6 pt-8 pb-0 text-center sm:px-10">
                    <CardTitle class="text-xl">{{ title }}</CardTitle>
                    <CardDescription>{{ description }}</CardDescription>
                </CardHeader>
                <CardContent class="px-6 pb-8 sm:px-10">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
