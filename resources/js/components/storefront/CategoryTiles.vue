<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { index } from '@/routes/categories';
import { show } from '@/routes/category';

/**
 * Category entry points. Square tiles rather than the usual circles: the
 * storefront's geometry is signage-flat throughout, and a square crop wastes
 * none of the product photography.
 */
defineProps<{ categories: App.Data.CategoryData[] }>();
</script>

<template>
    <ul
        v-if="categories.length"
        class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-8"
    >
        <li v-for="category in categories" :key="category.id">
            <Link
                :href="show(category.slug)"
                class="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric"
            >
                <div
                    class="aspect-square overflow-hidden rounded-xs bg-white ring-1 ring-rule transition-[box-shadow] group-hover:ring-2 group-hover:ring-electric"
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
                        class="flex size-full items-center justify-center p-4 text-muted-foreground"
                        v-html="category.iconSvg"
                    />
                </div>
                <p
                    class="mt-2 line-clamp-2 text-center text-xs leading-4 text-foreground"
                >
                    {{ category.name }}
                </p>
            </Link>
        </li>

        <li>
            <Link
                :href="index()"
                class="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric"
            >
                <div
                    class="flex aspect-square items-center justify-center rounded-xs border border-dashed border-rule transition-colors group-hover:border-electric group-hover:bg-accent"
                >
                    <ArrowRight
                        class="size-5 text-muted-foreground transition-colors group-hover:text-electric"
                        aria-hidden="true"
                    />
                </div>
                <p class="mt-2 text-center text-xs leading-4 text-muted-foreground">
                    All categories
                </p>
            </Link>
        </li>
    </ul>
</template>
