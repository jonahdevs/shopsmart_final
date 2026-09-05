<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PackageX } from '@lucide/vue';
import Price from '@/components/storefront/Price.vue';
import Rating from '@/components/storefront/Rating.vue';
import SavedRemoveButton from '@/components/storefront/SavedRemoveButton.vue';
import SavedToggleButton from '@/components/storefront/SavedToggleButton.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show } from '@/routes/product';

/**
 * The comparison matrix.
 *
 * `values` is aligned positionally to `products` on the server, so this is a
 * plain two-level `v-for` with no lookups: row n, column m is
 * `attributes[n].values[m]`. A null there means that product does not declare
 * the attribute at all, which is shown as an em dash — a blank cell reads as a
 * rendering fault rather than as an answer.
 *
 * The table sets its own minimum width and scrolls inside `Table`'s own
 * overflow container, so a four-product comparison never gives the document a
 * horizontal scrollbar. The attribute column is pinned so the row label stays
 * on screen while the products slide past it.
 */
defineProps<{
    products: App.Data.ProductCardData[];
    attributes: App.Data.CompareAttributeData[];
}>();
</script>

<template>
    <Table class="min-w-3xl border-separate border-spacing-0">
        <caption class="sr-only">
            Products side by side, with each specification on its own row.
        </caption>

        <TableHeader>
            <TableRow class="hover:bg-transparent">
                <TableHead
                    scope="col"
                    class="bg-background border-rule sticky left-0 z-10 h-auto w-36 border-b p-3 align-top"
                >
                    <span class="sr-only">Specification</span>
                </TableHead>

                <TableHead
                    v-for="product in products"
                    :key="product.id"
                    scope="col"
                    class="border-rule h-auto w-60 min-w-60 border-b p-3 align-top font-normal whitespace-normal"
                >
                    <div class="flex flex-col gap-3">
                        <Link
                            :href="show(product.slug)"
                            class="focus-visible:outline-electric block rounded-xs outline-offset-4 focus-visible:outline-2"
                            tabindex="-1"
                            aria-hidden="true"
                        >
                            <div
                                class="aspect-square w-full overflow-hidden rounded-xs bg-white"
                            >
                                <img
                                    v-if="product.image"
                                    :src="
                                        product.image.thumbUrl ??
                                        product.image.url
                                    "
                                    :alt="product.image.alt"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-full object-contain"
                                />
                                <div v-else class="bg-muted size-full" />
                            </div>
                        </Link>

                        <div class="space-y-1.5">
                            <p
                                v-if="product.brandName"
                                class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                            >
                                {{ product.brandName }}
                            </p>
                            <p class="text-foreground text-sm leading-5">
                                <Link
                                    :href="show(product.slug)"
                                    class="hover:text-electric focus-visible:outline-electric rounded-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                                >
                                    {{ product.name }}
                                </Link>
                            </p>

                            <Rating
                                :average="product.ratingAverage"
                                :count="product.ratingCount"
                            />

                            <Price
                                size="sm"
                                :formatted="product.effectivePriceFormatted"
                                :compare-formatted="
                                    product.isOnSale
                                        ? product.priceFormatted
                                        : null
                                "
                                :discount-percent="product.discountPercent"
                            />

                            <p
                                v-if="!product.inStock"
                                class="text-destructive flex items-center gap-1.5 text-xs"
                            >
                                <PackageX
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>Out of stock</span>
                            </p>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-2"
                        >
                            <SavedToggleButton
                                :product-id="product.id"
                                list="wishlist"
                            />
                            <SavedRemoveButton
                                :product-id="product.id"
                                :product-name="product.name"
                                list="compare"
                            />
                        </div>
                    </div>
                </TableHead>
            </TableRow>
        </TableHeader>

        <TableBody>
            <TableRow
                v-for="attribute in attributes"
                :key="attribute.name"
                class="hover:bg-transparent"
            >
                <TableHead
                    scope="row"
                    class="bg-background border-rule text-muted-foreground sticky left-0 z-10 h-auto border-b p-3 align-top text-xs font-semibold tracking-[0.08em] whitespace-normal uppercase"
                >
                    {{ attribute.name }}
                </TableHead>

                <TableCell
                    v-for="(value, column) in attribute.values"
                    :key="`${attribute.name}-${column}`"
                    class="border-rule text-foreground border-b p-3 align-top text-sm whitespace-normal"
                >
                    <template v-if="value !== null">{{ value }}</template>
                    <template v-else>
                        <span class="text-muted-foreground" aria-hidden="true">
                            &mdash;
                        </span>
                        <span class="sr-only">Not stated</span>
                    </template>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
