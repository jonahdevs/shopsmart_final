<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Price from '@/components/storefront/Price.vue';
import { show } from '@/routes/product';

/**
 * One priced line, read-only.
 *
 * The same shape serves the checkout quote and the placed order, so this is the
 * one component both pages use — nothing here can be edited, because by the
 * time a line reaches this component the quantity is either being confirmed or
 * already frozen onto an order.
 *
 * `slug` is null for a line whose product has since left the catalogue, which
 * only happens on an old order; that line stays readable and simply stops
 * linking anywhere.
 */
defineProps<{ line: App.Data.PricedLineData }>();
</script>

<template>
    <li class="flex gap-4 p-5">
        <!--
          The image is decoration next to a title that already names the
          product, so it carries no link of its own and no alternative text.
        -->
        <div
            class="size-16 shrink-0 overflow-hidden rounded-lg bg-white sm:size-20"
            aria-hidden="true"
        >
            <img
                v-if="line.image"
                :src="line.image.thumbUrl ?? line.image.url"
                :alt="line.image.alt"
                loading="lazy"
                decoding="async"
                class="size-full object-contain"
            />
            <div v-else class="bg-muted size-full rounded-lg" />
        </div>

        <div
            class="flex min-w-0 flex-1 flex-wrap items-start justify-between gap-x-6 gap-y-3"
        >
            <div class="min-w-0 space-y-1">
                <p
                    v-if="line.brandName"
                    class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                >
                    {{ line.brandName }}
                </p>

                <h3 class="text-foreground text-sm leading-5 font-medium">
                    <Link
                        v-if="line.slug"
                        :href="show(line.slug)"
                        class="hover:text-electric focus-visible:outline-electric rounded-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        {{ line.name }}
                    </Link>
                    <template v-else>{{ line.name }}</template>
                </h3>

                <p
                    v-if="line.optionLabel"
                    class="text-muted-foreground text-xs"
                >
                    {{ line.optionLabel }}
                </p>
                <p v-if="line.sku" class="text-muted-foreground text-xs">
                    SKU {{ line.sku }}
                </p>

                <p class="text-muted-foreground text-xs tabular-nums">
                    {{ line.quantity }} &times; {{ line.unitPriceFormatted }}
                </p>

                <p
                    v-if="line.discountCents > 0"
                    class="text-sale text-xs tabular-nums"
                >
                    Includes &minus;{{ line.discountFormatted }} off this line
                </p>
            </div>

            <div class="shrink-0">
                <p
                    class="font-display text-muted-foreground text-[0.625rem] font-bold tracking-[0.14em] uppercase"
                >
                    Line total
                </p>
                <div class="mt-1">
                    <Price size="sm" :formatted="line.totalFormatted" />
                </div>
            </div>
        </div>
    </li>
</template>
