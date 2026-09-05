<script setup lang="ts">
import { Deferred, Form, Head } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AccessoryUpsellDialog from '@/components/storefront/AccessoryUpsellDialog.vue';
import Price from '@/components/storefront/Price.vue';
import ProductGallery from '@/components/storefront/ProductGallery.vue';
import ProductQuantityStepper from '@/components/storefront/ProductQuantityStepper.vue';
import ProductRail from '@/components/storefront/ProductRail.vue';
import ProductReviews from '@/components/storefront/ProductReviews.vue';
import ProductReviewsSkeleton from '@/components/storefront/ProductReviewsSkeleton.vue';
import ProductSpecifications from '@/components/storefront/ProductSpecifications.vue';
import ProductStockBadge from '@/components/storefront/ProductStockBadge.vue';
import ProductVariantPicker from '@/components/storefront/ProductVariantPicker.vue';
import Rating from '@/components/storefront/Rating.vue';
import SectionHeading from '@/components/storefront/SectionHeading.vue';
import StoreBreadcrumbs from '@/components/storefront/StoreBreadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/cart';

const { product, accessories, related, brandProducts, alsoViewed, reviews } =
    defineProps<{
        product: App.Data.ProductDetailData;
        /** Curated accessories, offered once something has been added. */
        accessories: App.Data.ProductCardData[];
        related: App.Data.ProductCardData[];
        brandProducts: App.Data.ProductCardData[];
        alsoViewed: App.Data.ProductCardData[];
        /** Deferred by the controller — undefined until the follow-up lands. */
        reviews?: App.Data.ReviewData[];
    }>();

const selectedVariant = ref<App.Data.ProductVariantData | null>(null);
const quantity = ref<number>(product.minOrderQuantity);

/**
 * Variant photographs are folded into the gallery so the strip stays fixed
 * while options are tried on: choosing a variant is then a jump to a slide that
 * is already there rather than a swap that reshuffles the thumbnails.
 */
const galleryImages = computed<App.Data.ImageData[]>(() => {
    const images = [...product.images];
    const seen = new Set(images.map((image) => image.url));

    for (const variant of product.variants) {
        if (variant.image !== null && !seen.has(variant.image.url)) {
            seen.add(variant.image.url);
            images.push(variant.image);
        }
    }

    return images;
});

const activeImageUrl = computed<string | null>(
    () => selectedVariant.value?.image?.url ?? null,
);

/** True while a variable product still has no combination chosen. */
const awaitingSelection = computed<boolean>(
    () => product.variants.length > 0 && selectedVariant.value === null,
);

/**
 * A variant with no price of its own is sold at the parent product's, so the
 * price block reads from whichever of the two actually carries one.
 */
const priceSource = computed<
    App.Data.ProductDetailData | App.Data.ProductVariantData
>(() =>
    selectedVariant.value !== null &&
    selectedVariant.value.effectivePriceFormatted !== null
        ? selectedVariant.value
        : product,
);

const compareFormatted = computed<string | null>(() =>
    priceSource.value.isOnSale ? priceSource.value.priceFormatted : null,
);

/** Stock is read off the chosen variant, or off the product when it is simple. */
const stockSource = computed<
    App.Data.ProductDetailData | App.Data.ProductVariantData
>(() => selectedVariant.value ?? product);

/**
 * A convenience ceiling for the stepper only. The server decides what may
 * actually be bought and re-checks it when the line is added.
 */
const maxQuantity = computed<number | null>(() => {
    if (product.allowBackorder) {
        return null;
    }

    const available = stockSource.value.stockQuantity;

    return available !== null && available > 0 ? available : null;
});

const canAddToCart = computed<boolean>(
    () =>
        !awaitingSelection.value &&
        stockSource.value.inStock &&
        priceSource.value.effectivePriceFormatted !== null,
);

const displaySku = computed<string | null>(
    () => selectedVariant.value?.sku ?? product.sku,
);

const brandRailTitle = computed<string>(() =>
    product.brand === null
        ? 'More like this'
        : `More from ${product.brand.name}`,
);

/** Changing option can lower the ceiling under what is already in the field. */
watch(maxQuantity, (max) => {
    if (max !== null && quantity.value > max) {
        quantity.value = max;
    }
});

const upsellOpen = ref(false);

/**
 * The add-to-cart button, handed to the dialog so closing it puts the keyboard
 * back where it was. It is disabled while the request is in flight, so by the
 * time the dialog opens the browser has already dropped focus to the body and
 * there is nothing left for the dialog to infer.
 */
const addToCartButton = ref<InstanceType<typeof Button> | null>(null);

/**
 * What the confirmation line in the upsell reads back, snapshotted the moment
 * the add succeeded: the picker and the stepper stay live behind the dialog,
 * and the shopper should be told what actually went in the cart.
 */
const addedLine = ref<{
    name: string;
    optionLabel: string | null;
    quantity: number;
}>({ name: product.name, optionLabel: null, quantity: 1 });

/**
 * The upsell's trigger. `<Form>`'s success event is the only honest signal that
 * the line was opened — the server clamps the quantity and can still refuse the
 * add — and the dialog stays shut when there is nothing to offer, because an
 * empty upsell is an interruption that buys the shopper nothing.
 */
function openUpsell(): void {
    if (accessories.length === 0) {
        return;
    }

    addedLine.value = {
        name: product.name,
        optionLabel: selectedVariant.value?.optionLabel ?? null,
        quantity: quantity.value,
    };

    upsellOpen.value = true;
}
</script>

<template>
    <Head :title="product.metaTitle ?? product.name">
        <meta
            v-if="product.metaDescription ?? product.shortDescription"
            head-key="description"
            name="description"
            :content="product.metaDescription ?? product.shortDescription ?? ''"
        />
    </Head>

    <div class="container py-6">
        <StoreBreadcrumbs :items="product.breadcrumbs" />

        <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-14">
            <ProductGallery
                :images="galleryImages"
                :active-url="activeImageUrl"
            />

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <p
                        v-if="product.brand"
                        class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.14em] uppercase"
                    >
                        {{ product.brand.name }}
                    </p>

                    <h1
                        class="font-display text-foreground text-2xl font-black tracking-[-0.03em] break-words sm:text-3xl"
                    >
                        {{ product.name }}
                    </h1>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <Rating
                            :average="product.ratingAverage"
                            :count="product.ratingCount"
                        />
                        <!-- A same-page jump, not a route: no Wayfinder to ask. -->
                        <a
                            v-if="product.ratingCount > 0"
                            href="#reviews"
                            class="text-electric hover:border-electric focus-visible:outline-electric border-b border-transparent text-xs focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            Read reviews
                        </a>
                    </div>
                </div>

                <dl
                    v-if="displaySku ?? product.modelNumber"
                    class="text-muted-foreground flex flex-wrap gap-x-5 gap-y-1 text-xs"
                >
                    <div v-if="displaySku" class="flex gap-1.5">
                        <dt>SKU</dt>
                        <dd class="text-foreground tabular-nums">
                            {{ displaySku }}
                        </dd>
                    </div>
                    <div v-if="product.modelNumber" class="flex gap-1.5">
                        <dt>Model</dt>
                        <dd class="text-foreground tabular-nums">
                            {{ product.modelNumber }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="product.shortDescription"
                    class="text-muted-foreground max-w-prose text-sm leading-6"
                >
                    {{ product.shortDescription }}
                </p>

                <!-- The one loud thing on the page. -->
                <div class="flex flex-col gap-3">
                    <Price
                        size="lg"
                        :formatted="priceSource.effectivePriceFormatted"
                        :compare-formatted="compareFormatted"
                        :discount-percent="priceSource.discountPercent"
                    />

                    <ProductStockBadge
                        :status="stockSource.stockStatus"
                        :quantity="stockSource.stockQuantity"
                        :awaiting-selection="awaitingSelection"
                    />
                </div>

                <ProductVariantPicker
                    v-if="product.variationAttributes.length"
                    v-model="selectedVariant"
                    :attributes="product.variationAttributes"
                    :variants="product.variants"
                    :default-variant-id="product.defaultVariantId"
                />

                <!--
                  The quantity is a request, not an instruction: the server
                  clamps it to the stock rules, so the bounds the stepper
                  renders are a courtesy only.

                  `preserveState` keeps this page's instance across the redirect
                  back onto it, so the chosen option, the quantity and the
                  upsell dialog all survive the add instead of being remounted
                  out from under the shopper.
                -->
                <Form
                    v-bind="store.form()"
                    :options="{ preserveScroll: true, preserveState: true }"
                    v-slot="{ processing }"
                    class="flex flex-col gap-3"
                    @success="openUpsell"
                >
                    <input
                        type="hidden"
                        name="product_id"
                        :value="product.id"
                    />
                    <input
                        v-if="selectedVariant !== null"
                        type="hidden"
                        name="variant_id"
                        :value="selectedVariant.id"
                    />

                    <div class="flex flex-wrap items-center gap-3">
                        <ProductQuantityStepper
                            v-model="quantity"
                            :min="product.minOrderQuantity"
                            :max="maxQuantity"
                            :disabled="processing || !canAddToCart"
                        />

                        <Button
                            ref="addToCartButton"
                            type="submit"
                            size="lg"
                            class="font-display h-11 min-w-48 flex-1 rounded-xs font-bold tracking-[0.08em] uppercase"
                            :disabled="processing || !canAddToCart"
                        >
                            <ShoppingCart class="size-4" aria-hidden="true" />
                            Add to cart
                        </Button>
                    </div>

                    <p
                        v-if="awaitingSelection"
                        class="text-muted-foreground text-xs"
                    >
                        Choose every option above to add this to your cart.
                    </p>
                    <p
                        v-else-if="product.minOrderQuantity > 1"
                        class="text-muted-foreground text-xs"
                    >
                        Sold in a minimum of
                        {{ product.minOrderQuantity }} units.
                    </p>
                </Form>
            </div>
        </div>

        <section v-if="product.description" class="mt-16">
            <SectionHeading title="About this product" />
            <p
                class="text-foreground mt-6 max-w-3xl text-sm leading-7 whitespace-pre-line"
            >
                {{ product.description }}
            </p>
        </section>

        <section
            v-if="
                product.specifications.length || product.technicalSpecification
            "
            class="mt-16"
        >
            <SectionHeading title="Specifications" />
            <div class="mt-6">
                <ProductSpecifications
                    :specifications="product.specifications"
                    :technical-specification="product.technicalSpecification"
                />
            </div>
        </section>

        <section id="reviews" class="mt-16 scroll-mt-28">
            <SectionHeading title="Reviews" />

            <!--
              The controller defers the review list, so it is never dereferenced
              before it lands; the skeleton holds the block's height in the
              meantime.
            -->
            <div class="mt-6">
                <Deferred data="reviews">
                    <template #fallback>
                        <ProductReviewsSkeleton />
                    </template>

                    <ProductReviews
                        :reviews="reviews ?? []"
                        :average="product.ratingAverage"
                        :count="product.ratingCount"
                    />
                </Deferred>
            </div>
        </section>

        <div class="mt-16 flex flex-col gap-16">
            <ProductRail title="Related products" :products="related" />
            <ProductRail :title="brandRailTitle" :products="brandProducts" />
            <ProductRail title="Customers also viewed" :products="alsoViewed" />
        </div>

        <!--
          Only mounted for a product that actually has something to offer, so
          the dialog cannot be opened onto an empty list.
        -->
        <AccessoryUpsellDialog
            v-if="accessories.length"
            v-model:open="upsellOpen"
            :accessories="accessories"
            :added-name="addedLine.name"
            :added-option-label="addedLine.optionLabel"
            :added-quantity="addedLine.quantity"
            :return-focus-to="(addToCartButton?.$el as HTMLElement) ?? null"
        />
    </div>
</template>
