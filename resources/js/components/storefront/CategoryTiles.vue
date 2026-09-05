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
                class="group focus-visible:outline-electric block focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                <div
                    class="ring-rule group-hover:ring-electric aspect-square overflow-hidden rounded-xs bg-white ring-1 transition-[box-shadow] group-hover:ring-2"
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
                        class="text-muted-foreground flex size-full items-center justify-center p-4"
                        v-html="category.iconSvg"
                    />
                </div>
                <p
                    class="text-foreground mt-2 line-clamp-2 text-center text-xs leading-4"
                >
                    {{ category.name }}
                </p>
            </Link>
        </li>

        <li>
            <Link
                :href="index()"
                class="group focus-visible:outline-electric block focus-visible:outline-2 focus-visible:outline-offset-4"
            >
                <div
                    class="border-rule group-hover:border-electric group-hover:bg-accent flex aspect-square items-center justify-center rounded-xs border border-dashed transition-colors"
                >
                    <ArrowRight
                        class="text-muted-foreground group-hover:text-electric size-5 transition-colors"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="text-muted-foreground mt-2 text-center text-xs leading-4"
                >
                    All categories
                </p>
            </Link>
        </li>
    </ul>
</template>
