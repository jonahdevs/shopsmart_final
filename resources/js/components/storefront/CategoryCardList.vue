<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { groupNumber } from '@/components/storefront/catalogFilters';
import { show } from '@/routes/category';

/**
 * A row of category entry points, wider than the home page's square tiles so
 * the count has room to sit under the name. Used for a category's children and
 * for the index's second level.
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
                class="group focus-visible:outline-electric flex h-full items-center gap-3 rounded-xs bg-white p-2.5 focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                <div
                    class="ring-rule group-hover:ring-electric size-12 shrink-0 overflow-hidden rounded-xs bg-white ring-1 transition-[box-shadow]"
                >
                    <img
                        v-if="category.image"
                        :src="category.image.thumbUrl ?? category.image.url"
                        :alt="category.image.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <span
                        v-else-if="category.iconSvg"
                        class="text-muted-foreground flex size-full items-center justify-center p-2.5"
                        v-html="category.iconSvg"
                    />
                </div>

                <span class="min-w-0 flex-1">
                    <span
                        class="text-foreground group-hover:text-electric block text-sm leading-5 break-words transition-colors"
                    >
                        {{ category.name }}
                    </span>
                    <span
                        v-if="category.productCount !== null"
                        class="text-muted-foreground block text-xs tabular-nums"
                    >
                        {{ groupNumber(category.productCount) }}
                        {{ category.productCount === 1 ? 'item' : 'items' }}
                    </span>
                </span>
            </Link>
        </li>
    </ul>
</template>
