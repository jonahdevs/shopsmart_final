<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import CategoryCardList from '@/components/storefront/CategoryCardList.vue';
import { groupNumber } from '@/components/storefront/catalogFilters';
import { show } from '@/routes/category';

/**
 * The taxonomy index.
 *
 * The server sends roots only, each carrying its own children, and rolls each
 * count up over the whole subtree — so a number here matches what the category
 * page lists when the shopper clicks through.
 */
const { categories: roots } = defineProps<{
    categories: App.Data.CategoryData[];
}>();
</script>

<template>
    <Head title="All categories" />

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <header>
            <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
            <h1
                class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
            >
                All categories
            </h1>
            <p class="text-muted-foreground mt-2 text-sm">
                Every aisle in the shop, top to bottom.
            </p>
        </header>

        <div class="mt-12 flex flex-col gap-14">
            <section v-for="root in roots" :key="root.id">
                <!--
                  The root is a link in its own right, not just a label: a
                  top-level category rolls its whole subtree up into one
                  listing, which is often what the shopper wants first.
                -->
                <div class="border-rule flex items-center gap-4 border-b pb-4">
                    <div
                        v-if="root.image"
                        class="ring-rule size-14 shrink-0 overflow-hidden rounded-xs bg-white ring-1"
                    >
                        <img
                            :src="root.image.thumbUrl ?? root.image.url"
                            :alt="root.image.alt"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2
                            class="font-display text-foreground text-lg font-black tracking-[-0.03em] uppercase sm:text-xl"
                        >
                            <Link
                                :href="show(root.slug)"
                                class="hover:text-electric focus-visible:outline-electric rounded-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                {{ root.name }}
                            </Link>
                        </h2>
                        <p
                            v-if="root.productCount !== null"
                            class="text-muted-foreground mt-1 text-xs tabular-nums"
                        >
                            {{ groupNumber(root.productCount) }}
                            {{
                                root.productCount === 1 ? 'product' : 'products'
                            }}
                        </p>
                    </div>

                    <Link
                        :href="show(root.slug)"
                        class="font-display text-electric hover:border-electric focus-visible:outline-electric inline-flex shrink-0 items-center gap-1.5 border-b border-transparent pb-0.5 text-xs font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        Shop
                        <span class="sr-only">{{ root.name }}</span>
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>

                <p
                    v-if="root.description"
                    class="text-muted-foreground mt-4 max-w-2xl text-sm leading-relaxed"
                >
                    {{ root.description }}
                </p>

                <div v-if="root.children.length > 0" class="mt-5">
                    <h3 class="sr-only">Sub-categories of {{ root.name }}</h3>
                    <CategoryCardList :categories="root.children" />
                </div>
            </section>
        </div>

        <p
            v-if="roots.length === 0"
            class="border-rule text-muted-foreground mt-12 rounded-xs border border-dashed p-10 text-center text-sm"
        >
            No categories are published yet.
        </p>
    </div>
</template>
