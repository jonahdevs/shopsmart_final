<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Search, UserRound } from '@lucide/vue';
import { ref } from 'vue';
import CategoryStripe from '@/components/storefront/CategoryStripe.vue';
import type { NavCategory } from '@/components/storefront/CategoryStripe.vue';
import { dashboard, login } from '@/routes';
import { catalog, home } from '@/routes';

const { categories } = defineProps<{ categories: NavCategory[] }>();

const page = usePage();

const query = ref<string>(
    new URLSearchParams(window.location.search).get('q') ?? '',
);

function search(): void {
    router.get(catalog.url(), query.value ? { q: query.value } : {}, {
        preserveState: false,
    });
}
</script>

<template>
    <header class="sticky top-0 z-50 bg-ink text-white">
        <div
            class="flex items-center gap-4 px-4 py-3.5 sm:gap-6 sm:px-6 lg:px-8"
        >
            <Link
                :href="home()"
                class="font-display text-lg font-black tracking-[-0.045em] whitespace-nowrap uppercase focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric"
            >
                Shop<span class="text-electric">smart</span>
            </Link>

            <form
                class="relative ml-auto w-full max-w-xl"
                role="search"
                @submit.prevent="search"
            >
                <label for="store-search" class="sr-only">
                    Search the catalogue
                </label>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-white/45"
                    aria-hidden="true"
                />
                <input
                    id="store-search"
                    v-model="query"
                    type="search"
                    name="q"
                    placeholder="Search the catalogue"
                    autocomplete="off"
                    class="w-full rounded-xs border border-white/15 bg-white/8 py-2 pr-3 pl-9 text-sm text-white placeholder:text-white/45 focus:border-electric focus:bg-white/12 focus:outline-none"
                />
            </form>

            <Link
                :href="page.props.auth.user ? dashboard() : login()"
                class="flex shrink-0 items-center gap-2 rounded-xs px-1 py-1 text-sm text-white/80 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric"
            >
                <UserRound class="size-5" aria-hidden="true" />
                <span class="hidden sm:inline">
                    {{ page.props.auth.user ? 'Account' : 'Sign in' }}
                </span>
            </Link>
        </div>

        <CategoryStripe :categories="categories" />
    </header>
</template>
