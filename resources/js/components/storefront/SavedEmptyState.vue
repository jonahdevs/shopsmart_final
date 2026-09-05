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
    <Empty class="border-rule border">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <component :is="copy.icon" aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle
                class="font-display text-lg font-black tracking-[-0.02em] uppercase"
            >
                {{ copy.title }}
            </EmptyTitle>
            <EmptyDescription>{{ copy.description }}</EmptyDescription>
        </EmptyHeader>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <Link
                :href="catalog()"
                class="bg-electric font-display focus-visible:outline-electric rounded-xs px-4 py-2 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                Browse the shop
            </Link>

            <Link
                :href="categoriesIndex()"
                class="font-display text-electric hover:border-electric focus-visible:outline-electric border-b border-transparent pb-0.5 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                Browse categories
            </Link>
        </div>
    </Empty>
</template>
