<script setup lang="ts">
// Placeholder: the category listing UI is built out separately. The typed props
// below are the server contract and should survive that rewrite.
const props = defineProps<{
    category: App.Data.CategoryData;
    breadcrumbs: App.Data.BreadcrumbData[];
    products: App.Data.ProductListData;
    filters: App.Data.CatalogFilterData;
    categoryFacets: App.Data.FacetOptionData[];
    brandFacets: App.Data.FacetOptionData[];
}>();
</script>

<template>
    <div>
        <h1>{{ props.category.name }}</h1>
        <p>{{ breadcrumbs.length }} breadcrumbs, {{ category.children.length }} children</p>
        <p>{{ products.data.length }} of {{ products.total }} products</p>
        <p>{{ categoryFacets.length }} category facets, {{ brandFacets.length }} brand facets</p>
    </div>
</template>
