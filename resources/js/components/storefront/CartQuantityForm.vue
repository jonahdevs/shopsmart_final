<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { update } from '@/routes/cart';

/**
 * One cart line's quantity, as a form that submits itself.
 *
 * The mutation posts `product_id` / `variant_id` rather than the line `key`,
 * because that is what the server validates — the key is only this page's
 * rendering identity.
 *
 * Bounds here are a courtesy: `maxQuantity` is null when stock is untracked or
 * the product is backorderable, and the server re-clamps whatever arrives, so
 * nothing is trusted to the client. Stepping below one is not offered — that
 * is what the remove control is for, and it keeps the "0 removes the line"
 * server rule from firing by accident.
 */
const { item } = defineProps<{ item: App.Data.CartItemData }>();

const quantity = ref<number>(item.quantity);

/** The server is the authority, so resync whenever the prop comes back. */
watch(
    () => item.quantity,
    (value) => {
        quantity.value = value;
    },
);

/** A variant line's key is "productId|variantId", which is not id-safe. */
const fieldId = computed(
    () => `cart-quantity-${item.key.replace(/[^a-zA-Z0-9]+/g, '-')}`,
);

const canDecrease = computed(() => quantity.value > 1);

const canIncrease = computed(
    () =>
        item.inStock &&
        (item.maxQuantity === null || quantity.value < item.maxQuantity),
);

function clamp(value: number): number {
    if (!Number.isFinite(value)) {
        return 1;
    }

    const floored = Math.max(1, Math.floor(value));

    return item.maxQuantity === null
        ? floored
        : Math.min(item.maxQuantity, floored);
}

/**
 * `<Form>` reads the DOM when it submits, so the input has to have re-rendered
 * with the new number first — hence the tick between setting it and firing.
 */
async function step(delta: number, submit: () => void): Promise<void> {
    quantity.value = clamp(quantity.value + delta);

    await nextTick();

    submit();
}

/**
 * Clamped on change rather than on input, so a typed number is not fought
 * character by character. The field is written back by hand because a typed
 * value that clamps to the number already held would not re-render.
 */
async function onChange(event: Event, submit: () => void): Promise<void> {
    const field = event.target as HTMLInputElement;

    quantity.value = clamp(Number(field.value));

    await nextTick();

    field.value = String(quantity.value);

    submit();
}
</script>

<template>
    <Form
        v-bind="update.form()"
        :options="{ preserveScroll: true }"
        v-slot="{ processing, submit }"
        class="border-rule inline-flex items-stretch rounded-lg border bg-white"
    >
        <input type="hidden" name="product_id" :value="item.productId" />
        <input
            v-if="item.variantId !== null"
            type="hidden"
            name="variant_id"
            :value="item.variantId"
        />

        <button
            type="button"
            class="focus-visible:outline-electric text-ink enabled:hover:bg-tint enabled:hover:text-electric flex size-10 items-center justify-center rounded-l-lg transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="processing || !canDecrease"
            :aria-label="`Reduce quantity of ${item.name}`"
            @click="step(-1, submit)"
        >
            <Minus class="size-4" aria-hidden="true" />
        </button>

        <label :for="fieldId" class="sr-only">
            Quantity of {{ item.name }}
        </label>
        <input
            :id="fieldId"
            name="quantity"
            type="number"
            inputmode="numeric"
            step="1"
            min="1"
            :max="item.maxQuantity ?? undefined"
            :value="quantity"
            :disabled="processing"
            class="font-display text-ink focus-visible:outline-electric w-12 [appearance:textfield] border-none bg-transparent text-center text-sm font-bold tabular-nums focus-visible:outline-2 focus-visible:-outline-offset-2 disabled:opacity-40 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @change="onChange($event, submit)"
        />

        <button
            type="button"
            class="focus-visible:outline-electric text-ink enabled:hover:bg-tint enabled:hover:text-electric flex size-10 items-center justify-center rounded-r-lg transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="processing || !canIncrease"
            :aria-label="`Increase quantity of ${item.name}`"
            @click="step(1, submit)"
        >
            <Plus class="size-4" aria-hidden="true" />
        </button>
    </Form>
</template>
