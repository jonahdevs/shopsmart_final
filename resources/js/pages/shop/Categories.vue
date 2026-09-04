<script setup lang="ts">
// Placeholder: the category index UI is built out separately. The typed prop
// below is the server contract and should survive that rewrite.
defineProps<{
    categories: App.Data.CategoryData[];
}>();
</script>

<template>
    <div>
        <h1>All Categories</h1>
        <p>{{ categories.length }} categories</p>
    </div>
</template>
