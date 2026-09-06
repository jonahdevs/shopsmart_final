<script setup lang="ts">
import { groupNumber } from '@/components/storefront/catalogFilters';

/**
 * The name board for one category.
 *
 * The image sits beside the name rather than behind it: text over photography
 * needs a scrim to stay legible, and the storefront's rule is bordered cards
 * and straight-on product art, never a wash.
 *
 * The name carries the same rhythm SectionHeading owns for the sections below
 * it — blue eyebrow, heavy display line, muted subtitle.
 */
defineProps<{ category: App.Data.CategoryData }>();
</script>

<template>
    <div class="flex items-start gap-5 sm:gap-8">
        <div
            v-if="category.image"
            class="border-rule shadow-card hidden size-24 shrink-0 overflow-hidden rounded-lg border bg-white sm:block sm:size-32"
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
            <p
                class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
            >
                Category
            </p>
            <h1
                class="font-display text-ink mt-1 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
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
