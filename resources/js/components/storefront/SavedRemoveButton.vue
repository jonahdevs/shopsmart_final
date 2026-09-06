<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { destroy as compareDestroy } from '@/routes/compare';
import { destroy as wishlistDestroy } from '@/routes/wishlist';

/**
 * Takes one product off a saved list.
 *
 * Always `destroy`, never a toggle: the two endpoints are each idempotent, so a
 * double click removes something that is already gone rather than putting it
 * back.
 *
 * A pill, so it pairs with `SavedToggleButton` wherever the two sit together
 * under a card or above a comparison column.
 */
const { list } = defineProps<{
    productId: number;
    productName: string;
    list: 'wishlist' | 'compare';
}>();

const action = computed(() =>
    list === 'wishlist' ? wishlistDestroy.form() : compareDestroy.form(),
);
</script>

<template>
    <Form
        v-bind="action"
        :options="{ preserveScroll: true }"
        v-slot="{ processing }"
    >
        <input type="hidden" name="product_id" :value="productId" />

        <button
            type="submit"
            :disabled="processing"
            class="border-rule text-muted-foreground hover:border-destructive hover:text-destructive focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-full border bg-white px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
        >
            <Trash2 class="size-3.5" aria-hidden="true" />
            Remove
            <span class="sr-only">
                {{ productName }} from your
                {{ list === 'wishlist' ? 'wishlist' : 'compare list' }}
            </span>
        </button>
    </Form>
</template>
