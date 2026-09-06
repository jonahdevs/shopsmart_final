<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { Heart, Scale } from '@lucide/vue';
import { computed } from 'vue';
import {
    destroy as compareDestroy,
    store as compareStore,
} from '@/routes/compare';
import {
    destroy as wishlistDestroy,
    store as wishlistStore,
} from '@/routes/wishlist';

/**
 * Saves a product to the wishlist or the compare tray, or takes it back off.
 *
 * The server deliberately does not expose a toggle endpoint: `store` adds and
 * `destroy` removes, and both are idempotent. Which verb to send is decided
 * from `storefront.shopper`, which is shared on every response and read out of
 * the session — so two fast clicks send "add" twice or "remove" twice rather
 * than racing a toggle into the state nobody asked for. The button is also held
 * disabled while a submission is in flight.
 */
const {
    productId,
    list,
    iconOnly = false,
} = defineProps<{
    productId: number;
    list: 'wishlist' | 'compare';
    /**
     * Renders the control as the circular glyph that floats on a product
     * card's artwork. The label moves to `aria-label`, so the button keeps its
     * name; everywhere else the text stays visible as a pill chip.
     */
    iconOnly?: boolean;
}>();

const page = usePage();

const shopper = computed(() => page.props.storefront.shopper);

const saved = computed(() =>
    (list === 'wishlist'
        ? shopper.value.wishlistProductIds
        : shopper.value.compareProductIds
    ).includes(productId),
);

const action = computed(() => {
    if (list === 'wishlist') {
        return saved.value ? wishlistDestroy.form() : wishlistStore.form();
    }

    return saved.value ? compareDestroy.form() : compareStore.form();
});

const label = computed(() => {
    if (list === 'wishlist') {
        return saved.value ? 'Saved' : 'Save';
    }

    return saved.value ? 'Comparing' : 'Compare';
});

/**
 * The tray is capped, and the server drops the oldest entry rather than
 * refusing the new one — so this is a heads-up, not a block.
 */
const atCompareLimit = computed(
    () =>
        list === 'compare' &&
        !saved.value &&
        shopper.value.compareCount >= shopper.value.compareLimit,
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
            :aria-pressed="saved"
            :aria-label="iconOnly ? label : undefined"
            :title="
                atCompareLimit
                    ? `You are already comparing ${shopper.compareLimit} products. Adding this one drops the oldest.`
                    : undefined
            "
            class="focus-visible:outline-electric inline-flex items-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
            :class="
                iconOnly
                    ? [
                          'border-rule shadow-card hover:border-electric size-9 justify-center rounded-full border bg-white',
                          saved
                              ? 'text-electric'
                              : 'text-muted-foreground hover:text-electric',
                      ]
                    : saved
                      ? 'border-electric/40 bg-tint text-electric gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium'
                      : 'border-rule text-muted-foreground hover:border-electric hover:text-electric gap-1.5 rounded-full border bg-white px-3 py-1.5 text-xs font-medium'
            "
        >
            <component
                :is="list === 'wishlist' ? Heart : Scale"
                :class="[
                    iconOnly ? 'size-4' : 'size-3.5',
                    saved && list === 'wishlist' ? 'fill-current' : '',
                ]"
                aria-hidden="true"
            />
            <template v-if="!iconOnly">{{ label }}</template>
        </button>
    </Form>
</template>
