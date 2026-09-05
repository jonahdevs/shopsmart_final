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
const { productId, list } = defineProps<{
    productId: number;
    list: 'wishlist' | 'compare';
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
            :title="
                atCompareLimit
                    ? `You are already comparing ${shopper.compareLimit} products. Adding this one drops the oldest.`
                    : undefined
            "
            class="focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-xs text-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
            :class="
                saved
                    ? 'text-electric'
                    : 'text-muted-foreground hover:text-electric'
            "
        >
            <component
                :is="list === 'wishlist' ? Heart : Scale"
                class="size-3.5"
                :class="saved && list === 'wishlist' ? 'fill-current' : ''"
                aria-hidden="true"
            />
            {{ label }}
        </button>
    </Form>
</template>
