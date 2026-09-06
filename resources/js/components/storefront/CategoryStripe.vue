<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { ref } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as categoriesIndex } from '@/routes/categories';
import { show } from '@/routes/category';
import { catalog } from '@/routes';

export type NavCategory = {
    name: string;
    slug: string;
};

const { categories } = defineProps<{ categories: NavCategory[] }>();

const { isCurrentUrl } = useCurrentUrl();

/**
 * `All Categories` is the one nav control that has to work at 360px, where the
 * strip beside it is a horizontal scroller rather than a full menu. Making it a
 * sheet at every width keeps one behaviour to reason about and gives the small
 * viewport a keyboard-reachable menu of the whole taxonomy.
 */
const menuOpen = ref(false);
const menuTrigger = ref<HTMLButtonElement | null>(null);

/**
 * reka-ui only restores focus for a dialog opened through `<DialogTrigger>`;
 * this one is opened in code, so the close handler has to put focus back on the
 * pill itself or a keyboard shopper is dropped at the top of the document.
 */
function restoreFocus(event: Event): void {
    event.preventDefault();
    menuTrigger.value?.focus();
}

/**
 * Exact match, not `startsWith`: `navCategories` are roots, a child category
 * lives at its own top-level `/shop/{slug}`, and a prefix test would light up
 * `Home` for `Home & Living`.
 */
function isActive(slug: string): boolean {
    return isCurrentUrl(show(slug));
}
</script>

<template>
    <nav class="bg-tint border-rule border-b" aria-label="Product categories">
        <div
            class="container flex scrollbar-none items-stretch gap-1 overflow-x-auto"
        >
            <button
                ref="menuTrigger"
                type="button"
                class="bg-electric focus-visible:outline-electric my-2 flex shrink-0 items-center gap-2 rounded-lg px-4 py-2 text-[0.8125rem] font-bold whitespace-nowrap text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                :aria-expanded="menuOpen"
                aria-haspopup="dialog"
                @click="menuOpen = true"
            >
                <Menu class="size-4" aria-hidden="true" />
                All Categories
            </button>

            <Link
                v-for="category in categories"
                :key="category.slug"
                :href="show(category.slug)"
                class="text-ink hover:text-electric focus-visible:outline-electric relative flex shrink-0 items-center px-3 py-3.5 text-[0.8125rem] font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2"
                :class="{ 'text-electric': isActive(category.slug) }"
                :aria-current="isActive(category.slug) ? 'page' : undefined"
            >
                {{ category.name }}

                <span
                    v-if="isActive(category.slug)"
                    class="bg-electric absolute inset-x-3 bottom-0 h-0.5"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <!--
          Portalled to `document.body`, which is outside StoreShell's
          `.storefront` wrapper, so the class is restated here to re-establish
          the brand token remap without forking the `ui/` primitive.
        -->
        <Sheet v-model:open="menuOpen">
            <SheetContent
                side="left"
                class="storefront w-11/12 gap-0 sm:max-w-sm"
                @close-auto-focus="restoreFocus"
            >
                <SheetHeader class="border-rule border-b">
                    <SheetTitle
                        class="font-display text-base font-extrabold tracking-[-0.01em]"
                    >
                        All categories
                    </SheetTitle>
                    <SheetDescription>
                        Every aisle in the shop, top to bottom.
                    </SheetDescription>
                </SheetHeader>

                <ul class="min-h-0 flex-1 overflow-y-auto p-2">
                    <li v-for="category in categories" :key="category.slug">
                        <Link
                            :href="show(category.slug)"
                            class="hover:bg-accent hover:text-accent-foreground focus-visible:outline-electric block rounded-md px-3 py-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:-outline-offset-2"
                            :class="{
                                'text-electric': isActive(category.slug),
                            }"
                            :aria-current="
                                isActive(category.slug) ? 'page' : undefined
                            "
                            @click="menuOpen = false"
                        >
                            {{ category.name }}
                        </Link>
                    </li>
                </ul>

                <div class="border-rule flex flex-col gap-1 border-t p-2">
                    <Link
                        :href="catalog()"
                        class="hover:bg-accent focus-visible:outline-electric text-electric block rounded-md px-3 py-2.5 text-sm font-bold focus-visible:outline-2 focus-visible:-outline-offset-2"
                        @click="menuOpen = false"
                    >
                        All products
                    </Link>
                    <Link
                        :href="categoriesIndex()"
                        class="hover:bg-accent focus-visible:outline-electric text-electric block rounded-md px-3 py-2.5 text-sm font-bold focus-visible:outline-2 focus-visible:-outline-offset-2"
                        @click="menuOpen = false"
                    >
                        Browse all categories
                    </Link>
                </div>
            </SheetContent>
        </Sheet>
    </nav>
</template>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>
