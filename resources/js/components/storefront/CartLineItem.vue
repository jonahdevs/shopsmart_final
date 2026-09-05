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
 */
const { item } = defineProps<{ item: App.Data.CartItemData }>();

const priceDirection = computed(() =>
    item.currentUnitPriceCents > item.unitPriceCents ? 'higher' : 'lower',
);
</script>

<template>
    <li class="flex gap-4 py-6">
        <Link
            :href="show(item.slug)"
            class="focus-visible:outline-electric shrink-0 rounded-xs outline-offset-4 focus-visible:outline-2"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="size-20 overflow-hidden rounded-xs bg-white sm:size-24">
                <img
                    v-if="item.image"
                    :src="item.image.thumbUrl ?? item.image.url"
                    :alt="item.image.alt"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-contain"
                />
                <div v-else class="bg-muted size-full" />
            </div>
        </Link>

        <div class="flex min-w-0 flex-1 flex-col gap-4">
            <div
                class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3"
            >
                <div class="min-w-0 space-y-1">
                    <p
                        v-if="item.brandName"
                        class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                    >
                        {{ item.brandName }}
                    </p>
                    <h3 class="text-foreground text-sm leading-5">
                        <Link
                            :href="show(item.slug)"
                            class="hover:text-electric focus-visible:outline-electric rounded-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
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
                    <p
                        class="text-muted-foreground text-[0.625rem] font-semibold tracking-[0.14em] uppercase"
                    >
                        Line total
                    </p>
                    <div class="mt-1">
                        <Price size="sm" :formatted="item.lineTotalFormatted" />
                    </div>
                </div>
            </div>

            <div
                v-if="item.priceChanged"
                class="bg-muted flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xs px-3 py-2"
            >
                <p class="text-foreground flex items-center gap-2 text-xs">
                    <TriangleAlert
                        class="size-3.5 shrink-0"
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
                    <p
                        class="text-muted-foreground text-[0.625rem] font-semibold tracking-[0.14em] uppercase"
                    >
                        Each
                    </p>
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
