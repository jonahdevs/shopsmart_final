<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Truck } from '@lucide/vue';
import { index } from '@/routes/checkout';

/**
 * Delivery or collection.
 *
 * The choice is a GET link rather than a control, because the server re-prices
 * the whole quote around it — delivery is charged, collection is not — and
 * reading it off the query string keeps the two states as two addresses the
 * shopper can go back through.
 *
 * The visit preserves state so the address panel the shopper had open is still
 * open when the new quote lands, and the hidden field is what actually travels
 * with the order: the page's rendered choice, not the browser's guess.
 */
defineProps<{
    methods: { value: string; label: string }[];
    selected: App.Enums.DeliveryMethod;
    pickupAddress: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-4">
        <input type="hidden" name="delivery_method" :value="selected" />

        <div class="grid gap-3 sm:grid-cols-2">
            <Link
                v-for="method in methods"
                :key="method.value"
                :href="index.url({ query: { delivery: method.value } })"
                preserve-scroll
                preserve-state
                :aria-current="method.value === selected ? 'true' : undefined"
                class="focus-visible:outline-electric flex items-center gap-3 rounded-xs border p-4 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                :class="
                    method.value === selected
                        ? 'border-ink bg-card text-foreground'
                        : 'border-rule text-muted-foreground hover:border-ink'
                "
            >
                <component
                    :is="method.value === 'pickup' ? MapPin : Truck"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <span
                    class="font-display text-sm font-bold tracking-[0.04em] uppercase"
                >
                    {{ method.label }}
                </span>
            </Link>
        </div>

        <p
            v-if="selected === 'pickup'"
            class="bg-muted text-foreground flex items-start gap-2 rounded-xs px-4 py-3 text-sm leading-relaxed"
        >
            <MapPin class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                Collect from
                <span class="font-medium">{{ pickupAddress }}</span
                >. We will let you know as soon as it is ready to pick up.
            </span>
        </p>
        <p v-else class="text-muted-foreground text-sm">
            We deliver to the address you choose below.
        </p>
    </div>
</template>
