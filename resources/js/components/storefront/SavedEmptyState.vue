<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Heart, Scale } from '@lucide/vue';
import { computed } from 'vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { catalog } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';

/**
 * The dead end shared by the wishlist and the compare tray. Both are opt-in
 * lists, so the copy explains what fills them rather than apologising for being
 * empty.
 *
 * Same card as `CartEmptyState`: solid hairline, `shadow-card`, blue icon tile.
 */
const { list } = defineProps<{ list: 'wishlist' | 'compare' }>();

const copy = computed(() =>
    list === 'wishlist'
        ? {
              icon: Heart,
              title: 'Nothing saved yet',
              description:
                  'Save anything you are still thinking about and it will wait for you here, signed in or not.',
          }
        : {
              icon: Scale,
              title: 'Nothing to compare yet',
              description:
                  'Add products from the catalogue and their specifications line up side by side here.',
          },
);
</script>

<template>
    <Empty
        class="border-rule shadow-card rounded-lg border border-solid bg-white"
    >
        <EmptyHeader>
            <EmptyMedia
                variant="icon"
                class="bg-tint text-electric size-12 rounded-lg"
            >
                <component :is="copy.icon" aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-ink text-xl font-extrabold tracking-[-0.02em]"
            >
                {{ copy.title }}
            </EmptyTitle>
            <EmptyDescription>{{ copy.description }}</EmptyDescription>
        </EmptyHeader>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <Link
                :href="catalog()"
                class="bg-electric font-display focus-visible:outline-electric rounded-lg px-5 py-2.5 text-sm font-bold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Browse the shop
            </Link>

            <Link
                :href="categoriesIndex()"
                class="border-rule font-display text-ink hover:border-electric hover:text-electric focus-visible:outline-electric rounded-lg border bg-white px-5 py-2.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
