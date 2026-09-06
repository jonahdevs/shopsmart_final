<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PackageX, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import CartQuantityForm from '@/components/storefront/CartQuantityForm.vue';
import CartRemoveButton from '@/components/storefront/CartRemoveButton.vue';
import Price from '@/components/storefront/Price.vue';
import { show } from '@/routes/product';

/**
 * One line of the cart.
 *
 * Two prices are on show because the server sends two: `unitPriceFormatted` is
 * what the line was opened at and is what the subtotal is built from, while
 * `currentUnitPriceFormatted` is today's catalogue price. When they disagree
 * the line says so here, rather than letting the number move under the shopper
 * somewhere later.
 *
 * The image links to the product as well as the title does, so it is hidden
 * from assistive tech rather than read out as a second identical destination.
 *
 * The line is a row inside the cart card, so it carries no border of its own —
 * the list divides its children on `--rule` and the card holds the edge.
 */
const { item } = defineProps<{ item: App.Data.CartItemData }>();

const priceDirection = computed(() =>
    item.currentUnitPriceCents > item.unitPriceCents ? 'higher' : 'lower',
);
</script>

<template>
    <li class="flex gap-4 p-4 sm:gap-5 sm:p-6">
        <Link
            :href="show(item.slug)"
            class="focus-visible:outline-electric shrink-0 rounded-lg outline-offset-4 focus-visible:outline-2"
            tabindex="-1"
            aria-hidden="true"
        >
            <div
                class="border-rule size-20 overflow-hidden rounded-lg border bg-white p-1.5 sm:size-24"
            >
                <img
                    v-if="item.image"
                    :src="item.image.thumbUrl ?? item.image.url"
                    :alt="item.image.alt"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-contain"
                />
                <div v-else class="bg-tint size-full rounded-md" />
            </div>
        </Link>

        <div class="flex min-w-0 flex-1 flex-col gap-4">
            <div
                class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3"
            >
                <div class="min-w-0 space-y-1">
                    <p
                        v-if="item.brandName"
                        class="text-muted-foreground truncate text-xs"
                    >
                        {{ item.brandName }}
                    </p>
                    <h3 class="text-ink text-sm leading-5 font-medium">
                        <Link
                            :href="show(item.slug)"
                            class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            {{ item.name }}
                        </Link>
                    </h3>
                    <p
                        v-if="item.optionLabel"
                        class="text-muted-foreground text-xs"
                    >
                        {{ item.optionLabel }}
                    </p>
                    <p v-if="item.sku" class="text-muted-foreground text-xs">
                        SKU {{ item.sku }}
                    </p>
                </div>

                <div class="shrink-0">
                    <p class="text-muted-foreground text-xs">Line total</p>
                    <div class="mt-1">
                        <Price size="sm" :formatted="item.lineTotalFormatted" />
                    </div>
                </div>
            </div>

            <div
                v-if="item.priceChanged"
                class="bg-tint border-rule flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-3 py-2"
            >
                <p class="text-ink flex items-center gap-2 text-xs">
                    <TriangleAlert
                        class="text-electric size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        The catalogue price is now {{ priceDirection }}:
                    </span>
                </p>
                <Price size="sm" :formatted="item.currentUnitPriceFormatted" />
                <p class="text-muted-foreground text-xs">
                    You keep the price captured when this line was opened.
                </p>
            </div>

            <p
                v-if="!item.inStock"
                class="text-destructive flex items-center gap-2 text-xs"
            >
                <PackageX class="size-3.5 shrink-0" aria-hidden="true" />
                <span>Out of stock &mdash; remove it to check out.</span>
            </p>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div>
                    <p class="text-muted-foreground text-xs">Each</p>
                    <div class="mt-1">
                        <Price size="sm" :formatted="item.unitPriceFormatted" />
                    </div>
                </div>

                <CartQuantityForm :item="item" />
                <CartRemoveButton :item="item" />
            </div>
        </div>
    </li>
</template>
