<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';

/**
 * How many of the thing to buy.
 *
 * The field is a real `<input name="quantity">` so that Phase 3 can wrap the
 * price block in Inertia's `<Form>` and have it submit without any of this
 * changing. Bounds are a convenience only — the server is the authority on how
 * much of a thing can actually be bought and re-checks on add.
 */
const quantity = defineModel<number>({ required: true });

const {
    min = 1,
    max = null,
    disabled = false,
} = defineProps<{
    min?: number;
    /** Null when the ceiling is unknown or the product is backorderable. */
    max?: number | null;
    disabled?: boolean;
}>();

const canDecrease = computed(() => !disabled && quantity.value > min);
const canIncrease = computed(
    () => !disabled && (max === null || quantity.value < max),
);

function clamp(value: number): number {
    if (!Number.isFinite(value)) {
        return min;
    }

    const floored = Math.max(min, Math.floor(value));

    return max === null ? floored : Math.min(max, floored);
}

function step(delta: number): void {
    quantity.value = clamp(quantity.value + delta);
}

/**
 * Clamped on change rather than on input, so a typed number is not fought
 * character by character.
 */
function onChange(event: Event): void {
    const field = event.target as HTMLInputElement;

    quantity.value = clamp(Number(field.value));
    field.value = String(quantity.value);
}
</script>

<template>
    <div
        class="border-rule inline-flex items-stretch rounded-xs border"
        :class="disabled ? 'opacity-50' : ''"
    >
        <button
            type="button"
            class="focus-visible:outline-electric text-foreground enabled:hover:bg-muted flex size-11 items-center justify-center rounded-l-xs transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!canDecrease"
            :aria-label="`Decrease quantity to ${Math.max(min, quantity - 1)}`"
            @click="step(-1)"
        >
            <Minus class="size-4" aria-hidden="true" />
        </button>

        <label class="sr-only" for="product-quantity">Quantity</label>
        <input
            id="product-quantity"
            name="quantity"
            type="number"
            inputmode="numeric"
            class="font-display focus-visible:outline-electric w-14 [appearance:textfield] border-none bg-transparent text-center text-sm font-bold tabular-nums focus-visible:outline-2 focus-visible:-outline-offset-2 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            :value="quantity"
            :min="min"
            :max="max ?? undefined"
            :disabled="disabled"
            step="1"
            @change="onChange"
        />

        <button
            type="button"
            class="focus-visible:outline-electric text-foreground enabled:hover:bg-muted flex size-11 items-center justify-center rounded-r-xs transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!canIncrease"
            :aria-label="`Increase quantity to ${max === null ? quantity + 1 : Math.min(max, quantity + 1)}`"
            @click="step(1)"
        >
            <Plus class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
