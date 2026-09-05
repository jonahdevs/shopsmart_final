<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { groupNumber } from '@/components/storefront/catalogFilters';
import { show } from '@/routes/category';

/**
 * A grid of category entry points, sharing the home page's tile so a
 * sub-category reads as the same object as a department. The only addition is
 * the item count, which sits under the name where a tile has nothing.
 */
defineProps<{ categories: App.Data.CategoryData[] }>();
</script>

<template>
    <ul
        v-if="categories.length > 0"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
    >
        <li v-for="category in categories" :key="category.id">
            <Link
                :href="show(category.slug)"
                class="group border-rule shadow-card hover:shadow-card-hover focus-visible:outline-electric flex h-full flex-col rounded-lg border bg-white p-2 transition-shadow duration-200 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
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

                <div class="px-1 pt-2.5 pb-1">
                    <p
                        class="text-ink line-clamp-2 text-sm leading-5 font-medium"
                    >
                        {{ category.name }}
                    </p>
                    <p
                        v-if="category.productCount !== null"
                        class="text-muted-foreground mt-0.5 text-xs tabular-nums"
                    >
                        {{ groupNumber(category.productCount) }}
                        {{ category.productCount === 1 ? 'item' : 'items' }}
                    </p>
                </div>
            </Link>
        </li>
    </ul>
</template>
