<script setup lang="ts">
import { Label } from '@/components/ui/label';

/**
 * Does this thing move in a box. The "Shipping" facet of the product data card.
 *
 * The reference build's shipping tab holds weight and dimensions; this catalog
 * has neither column, so what is left is the pair of flags that decide whether
 * a fulfilment question is asked about the product at all. They belong together
 * and nowhere else: "virtual" sat under stock on the old screen, where it read
 * as a fact about the count rather than the reason there is no delivery.
 *
 * Both are raw checkboxes rather than the ui/ one, because the server reads an
 * unticked box as absent — which is what a bare input sends and what the
 * request's boolean casting is written against.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
}>();
</script>

<template>
    <div class="space-y-4">
        <p class="text-muted-foreground text-sm">
            A virtual product is never handed over — a service, a fee, a
            warranty — so it is left out of shipping entirely.
        </p>

        <div class="flex items-center gap-2">
            <input
                id="requires_shipping"
                name="requires_shipping"
                type="checkbox"
                value="1"
                :checked="product.requiresShipping"
                class="border-input size-4 rounded"
            />
            <Label for="requires_shipping">This product is delivered</Label>
        </div>

        <div class="flex items-center gap-2">
            <input
                id="is_virtual"
                name="is_virtual"
                type="checkbox"
                value="1"
                :checked="product.isVirtual"
                class="border-input size-4 rounded"
            />
            <Label for="is_virtual">Virtual (a service, never shipped)</Label>
        </div>
    </div>
</template>
