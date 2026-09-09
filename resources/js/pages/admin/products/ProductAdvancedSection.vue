<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The "Advanced" facet of the product data card.
 *
 * Sort order used to sit with the brand and the categories, on the reasoning
 * that it is part of filing. It is not: filing says where a product belongs,
 * this says where it queues among the products it already belongs with, and it
 * is set once by whoever is arranging a shelf. The reference build files it
 * here for the same reason, and this is the tab a staff member visits least.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-1.5">
        <Label for="sort_order">Sort order</Label>
        <Input
            id="sort_order"
            name="sort_order"
            type="number"
            min="0"
            class="sm:max-w-40"
            :default-value="product.sortOrder"
        />
        <p class="text-muted-foreground text-sm">
            Lower comes first in a listing that has not been sorted another way.
        </p>
        <InputError :message="errors.sort_order" />
    </div>
</template>
