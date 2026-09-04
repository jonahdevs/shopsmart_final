<script setup lang="ts">
// Placeholder: the faceted catalog UI is built out separately. The typed props
// below are the server contract and should survive that rewrite.
defineProps<{
    products: App.Data.ProductListData;
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
}>();
</script>

<template>
    <div>
        <h1>Shop</h1>
        <p>{{ products.data.length }} of {{ products.total }} products</p>
        <p>{{ categoryFacets.length }} category facets, {{ brandFacets.length }} brand facets</p>
    </div>
</template>
