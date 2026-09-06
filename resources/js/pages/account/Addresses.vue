<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MapPin, Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import AddressCard from '@/components/storefront/AddressCard.vue';
import CheckoutAddressFields from '@/components/storefront/CheckoutAddressFields.vue';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { store } from '@/routes/addresses';

/**
 * The address book.
 *
 * The server sends the default first and then the newest, which is the order a
 * shopper reads them in — the one checkout will use, then the ones they might
 * switch to. Editing, promoting and removing all live on the card itself; this
 * page owns only the "add a new one" panel.
 */
const { addresses } = defineProps<{
    addresses: App.Data.AddressData[];
    breadcrumbs: App.Data.BreadcrumbData[];
}>();

/**
 * Collapsed by default so the book itself is the page. Opened automatically
 * when there is nothing to read yet — an empty page whose only control is a
 * button that reveals the real control is a wasted tap.
 */
const adding = ref(addresses.length === 0);
</script>

<template>
    <Head title="Your addresses" />

    <div class="flex flex-col gap-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-muted-foreground -mt-2 text-sm tabular-nums">
                <template v-if="addresses.length === 0">
                    Nothing saved yet.
                </template>
                <template v-else>
                    {{ addresses.length }}
                    {{ addresses.length === 1 ? 'address' : 'addresses' }}
                    saved. Checkout reaches for the default first.
                </template>
            </p>

            <button
                type="button"
                :aria-expanded="adding"
                aria-controls="new-address-form"
                class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="adding = !adding"
            >
                <component
                    :is="adding ? X : Plus"
                    class="size-4"
                    aria-hidden="true"
                />
                {{ adding ? 'Cancel' : 'Add an address' }}
            </button>
        </div>

        <!--
          `preserveState` keeps this panel open when the server answers with a
          validation error; without it the adapter re-keys the page and the
          half-typed address disappears along with the messages explaining it.
        -->
        <Form
            v-if="adding"
            id="new-address-form"
            v-bind="store.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ errors, processing }"
            class="border-rule flex flex-col gap-5 rounded-lg border border-dashed p-5"
            @success="adding = false"
        >
            <div>
                <h2
                    class="font-display text-foreground text-lg font-extrabold tracking-[-0.02em]"
                >
                    New address
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    We keep it for next time, so checkout fills itself in.
                </p>
            </div>

            <CheckoutAddressFields :errors="errors" />

            <button
                type="submit"
                :disabled="processing"
                class="bg-ink font-display focus-visible:outline-electric h-10 w-fit rounded-lg px-5 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
            >
                Save this address
            </button>
        </Form>

        <section aria-labelledby="address-book-heading">
            <h2 id="address-book-heading" class="sr-only">Saved addresses</h2>

            <Empty
                v-if="addresses.length === 0"
                class="border-rule rounded-lg border"
            >
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <MapPin aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle
                        class="font-display text-lg font-extrabold tracking-[-0.02em]"
                    >
                        No addresses yet
                    </EmptyTitle>
                    <EmptyDescription>
                        Save one and every order after it is two taps shorter.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>

            <ul v-else class="flex flex-col gap-4">
                <AddressCard
                    v-for="address in addresses"
                    :key="address.id ?? address.summary"
                    :address="address"
                />
            </ul>
        </section>
    </div>
</template>
