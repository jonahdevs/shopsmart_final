<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { index } from '@/routes/categories';
import { show } from '@/routes/category';

/**
 * Category entry points.
 *
 * Each tile is the storefront's card — hairline border, `shadow-card`, lifting
 * on hover — with the artwork inset inside it at a landscape crop and the name
 * left-aligned underneath. Six across at the widest, halving down to two on a
 * phone, so a tile never gets so small the photography stops being readable.
 */
defineProps<{ categories: App.Data.CategoryData[] }>();

const TILE_CLASS =
    'group border-rule shadow-card hover:shadow-card-hover focus-visible:outline-electric flex h-full flex-col rounded-lg border bg-white p-2 transition-shadow duration-200 focus-visible:outline-2 focus-visible:outline-offset-2';
</script>

<template>
    <ul
        v-if="categories.length"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
    >
        <li v-for="category in categories" :key="category.id">
            <Link :href="show(category.slug)" :class="TILE_CLASS">
                <div class="bg-tint aspect-[4/3] overflow-hidden rounded-md">
                    <img
                        v-if="category.image"
                        :src="category.image.thumbUrl ?? category.image.url"
                        :alt="category.image.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none"
                    />
                    <span
                        v-else-if="category.iconSvg"
                        class="text-electric flex size-full items-center justify-center p-5"
                        aria-hidden="true"
                        v-html="category.iconSvg"
                    />
                </div>
                <p
                    class="text-ink line-clamp-2 px-1 pt-2.5 pb-1 text-sm leading-5 font-medium"
                >
                    {{ category.name }}
                </p>
            </Link>
        </li>

        <li>
            <Link :href="index()" :class="TILE_CLASS">
                <div
                    class="border-rule group-hover:border-electric group-hover:bg-tint flex aspect-[4/3] items-center justify-center rounded-md border border-dashed transition-colors"
                >
                    <ArrowRight
                        class="text-muted-foreground group-hover:text-electric size-5 transition-colors"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="text-muted-foreground px-1 pt-2.5 pb-1 text-sm leading-5 font-medium"
                >
                    All categories
                </p>
            </Link>
        </li>
    </ul>
</template>
