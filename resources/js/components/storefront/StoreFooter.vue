<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import { index } from '@/routes/categories';
import { catalog } from '@/routes';
import { show } from '@/routes/category';

const { categories } = defineProps<{ categories: NavCategory[] }>();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-20 bg-ink text-white">
        <div class="h-0.5 bg-electric" aria-hidden="true" />

        <div class="grid gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div class="lg:col-span-2">
                <p
                    class="font-display text-lg font-black tracking-[-0.045em] uppercase"
                >
                    Shop<span class="text-electric">smart</span>
                </p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-white/60">
                    Everyday essentials, delivered across Kenya. Pay with M-Pesa
                    or card at checkout.
                </p>
            </div>

            <div>
                <h2
                    class="font-display text-[0.6875rem] font-bold tracking-[0.16em] text-white/45 uppercase"
                >
                    Shop
                </h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li v-for="category in categories.slice(0, 6)" :key="category.slug">
                        <Link
                            :href="show(category.slug)"
                            class="text-white/70 transition-colors hover:text-white"
                        >
                            {{ category.name }}
                        </Link>
                    </li>
                </ul>
            </div>

            <div>
                <h2
                    class="font-display text-[0.6875rem] font-bold tracking-[0.16em] text-white/45 uppercase"
                >
                    Browse
                </h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li>
                        <Link
                            :href="catalog()"
                            class="text-white/70 transition-colors hover:text-white"
                        >
                            All products
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="index()"
                            class="text-white/70 transition-colors hover:text-white"
                        >
                            All categories
                        </Link>
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="border-t border-white/10 px-4 py-6 text-xs text-white/45 sm:px-6 lg:px-8"
        >
            &copy; {{ year }} ShopSmart
        </div>
    </footer>
</template>
