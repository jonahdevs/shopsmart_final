<script setup lang="ts">
// Placeholder: the product detail UI is built out separately. The typed props
// below are the server contract and should survive that rewrite.
const props = defineProps<{
    product: App.Data.ProductDetailData;
    related: App.Data.ProductCardData[];
    brandProducts: App.Data.ProductCardData[];
    alsoViewed: App.Data.ProductCardData[];
    reviews?: App.Data.ReviewData[];
}>();
</script>

<template>
    <div>
        <h1>{{ props.product.name }}</h1>
        <p>{{ product.images.length }} images, {{ product.variants.length }} variants</p>
        <p>
            {{ related.length }} related, {{ brandProducts.length }} from brand,
            {{ alsoViewed.length }} also viewed, {{ reviews?.length ?? 0 }} reviews
        </p>
    </div>
</template>
