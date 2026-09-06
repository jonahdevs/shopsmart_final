<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { destroy } from '@/routes/cart';

/**
 * Drops one line out of the cart.
 *
 * Posts the ids rather than the line key, and stays available for a line whose
 * product has since gone out of stock or off the catalogue — that is exactly
 * the line a shopper most needs to get rid of, and the server's remove endpoint
 * deliberately validates nothing beyond the ids.
 *
 * Rendered as a pill so it reads as a chip beside the `--radius` quantity
 * stepper rather than as another box of the same shape.
 */
const { item } = defineProps<{ item: App.Data.CartItemData }>();
</script>

<template>
    <Form
        v-bind="destroy.form()"
        :options="{ preserveScroll: true }"
        v-slot="{ processing }"
    >
        <input type="hidden" name="product_id" :value="item.productId" />
        <input
            v-if="item.variantId !== null"
            type="hidden"
            name="variant_id"
            :value="item.variantId"
        />

        <button
            type="submit"
            :disabled="processing"
            class="border-rule text-muted-foreground hover:border-destructive hover:text-destructive focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-full border bg-white px-3 py-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
        >
            <Trash2 class="size-3.5" aria-hidden="true" />
            Remove
            <span class="sr-only">{{ item.name }} from your cart</span>
        </button>
    </Form>
</template>
