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

    <div class="container py-8">
        <!--
          The page title carries the same rhythm SectionHeading owns for the
          <h2>s below it: blue eyebrow, heavy display line, muted subtitle.
        -->
        <header>
            <p
                class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
            >
                Explore
            </p>
            <h1
                class="font-display text-ink mt-1 text-2xl font-extrabold tracking-[-0.03em] sm:text-4xl"
            >
                All categories
            </h1>
            <p class="text-muted-foreground mt-2 text-sm sm:text-base">
                Every aisle in the shop, top to bottom.
            </p>
        </header>

        <div class="mt-10 flex flex-col gap-14">
            <section v-for="root in roots" :key="root.id">
                <!--
                  The root is a link in its own right, not just a label: a
                  top-level category rolls its whole subtree up into one
                  listing, which is often what the shopper wants first.
                -->
                <div
                    class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <div
                            v-if="root.image"
                            class="border-rule shadow-card size-14 shrink-0 overflow-hidden rounded-lg border bg-white"
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
                            <p
                                v-if="root.productCount !== null"
                                class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase tabular-nums"
                            >
                                {{ groupNumber(root.productCount) }}
                                {{
                                    root.productCount === 1
                                        ? 'product'
                                        : 'products'
                                }}
                            </p>
                            <h2
                                class="font-display text-ink text-xl font-extrabold tracking-[-0.03em] sm:text-2xl"
                            >
                                <Link
                                    :href="show(root.slug)"
                                    class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                                >
                                    {{ root.name }}
                                </Link>
                            </h2>
                        </div>
                    </div>

                    <Link
                        :href="show(root.slug)"
                        class="text-electric focus-visible:outline-electric group inline-flex shrink-0 items-center gap-1.5 rounded-sm text-sm font-bold transition-opacity hover:opacity-80 focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        Shop
                        <span class="sr-only">{{ root.name }}</span>
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </div>

                <p
                    v-if="root.description"
                    class="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed"
                >
                    {{ root.description }}
                </p>

                <div v-if="root.children.length > 0" class="mt-6">
                    <h3 class="sr-only">Sub-categories of {{ root.name }}</h3>
                    <CategoryCardList :categories="root.children" />
                </div>
            </section>
        </div>

        <p
            v-if="roots.length === 0"
            class="border-rule shadow-card text-muted-foreground mt-12 rounded-lg border bg-white p-10 text-center text-sm"
        >
            No categories are published yet.
        </p>
    </div>
</template>
