<script setup lang="ts">
import { groupNumber } from '@/components/storefront/catalogFilters';

/**
 * The name board for one category.
 *
 * The image sits beside the name rather than behind it: text over photography
 * needs a scrim to stay legible, and the storefront's rule is solid slabs and
 * straight-on product art, never a wash.
 */
defineProps<{ category: App.Data.CategoryData }>();
</script>

<template>
    <div class="flex items-start gap-5 sm:gap-8">
        <div
            v-if="category.image"
            class="ring-rule hidden size-24 shrink-0 overflow-hidden rounded-xs bg-white ring-1 sm:block sm:size-32"
        >
            <img
                :src="category.image.thumbUrl ?? category.image.url"
                :alt="category.image.alt"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                class="size-full object-cover"
            />
        </div>

        <div class="min-w-0 flex-1">
            <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
            <h1
                class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
            >
                {{ category.name }}
            </h1>
            <p
                v-if="category.productCount !== null"
                class="text-muted-foreground mt-2 text-sm tabular-nums"
            >
                {{ groupNumber(category.productCount) }}
                {{ category.productCount === 1 ? 'product' : 'products' }}
            </p>
            <p
                v-if="category.description"
                class="text-muted-foreground mt-3 max-w-2xl text-sm leading-relaxed"
            >
                {{ category.description }}
            </p>
        </div>
    </div>
</template>
