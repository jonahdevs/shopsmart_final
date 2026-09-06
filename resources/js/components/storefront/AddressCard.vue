<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Check, MapPin, Pencil, Trash2, X } from '@lucide/vue';
import { ref, useId } from 'vue';
import CheckoutAddressFields from '@/components/storefront/CheckoutAddressFields.vue';
import { defaultMethod, destroy, update } from '@/routes/addresses';

/**
 * One entry in the address book, with the three things you can do to it.
 *
 * Editing is an inline disclosure rather than a dialog: an address is eight
 * fields, and a dialog that tall on a 360px phone is a page with a scrim behind
 * it. Keeping it in the flow also means the form is reachable by tabbing
 * forward from the entry it edits.
 *
 * Every form here carries `preserveState`, because without it the Vue adapter
 * re-keys the page on the response and the open editor — or the half-finished
 * delete confirmation — vanishes mid-interaction.
 */
const { address } = defineProps<{ address: App.Data.AddressData }>();

const id = useId();

const editing = ref(false);

/**
 * Deleting is destructive and irreversible, so the button asks first. Inline
 * rather than `confirm()`: a native dialog is unstyleable, blocks the main
 * thread and reads as a browser warning rather than as part of the page.
 */
const confirmingRemoval = ref(false);

const editorId = `${id}-editor`;

/**
 * The row is only actionable once it has been saved. `AddressData` also serves
 * the checkout's unsaved draft, where the id is null.
 */
const addressId = address.id;
</script>

<template>
    <li
        class="border-rule shadow-card flex flex-col gap-4 rounded-lg border bg-white p-5"
    >
        <div class="flex items-start gap-3">
            <MapPin
                class="mt-0.5 size-4 shrink-0"
                :class="
                    address.isDefault
                        ? 'text-electric'
                        : 'text-muted-foreground'
                "
                aria-hidden="true"
            />

            <div class="min-w-0 flex-1 text-sm">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h3 class="text-foreground font-medium">
                        {{ address.fullName }}
                    </h3>
                    <span
                        v-if="address.label"
                        class="font-display text-muted-foreground text-[0.6875rem] font-bold tracking-[0.12em] uppercase"
                    >
                        {{ address.label }}
                    </span>
                    <span
                        v-if="address.isDefault"
                        class="bg-tint-strong text-electric flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                    >
                        <Check class="size-3.5" aria-hidden="true" />
                        Default
                    </span>
                </div>

                <p class="text-muted-foreground mt-1 leading-relaxed">
                    {{ address.summary }}
                </p>
                <p
                    v-if="address.phone"
                    class="text-muted-foreground mt-1 tabular-nums"
                >
                    {{ address.phone }}
                </p>
                <p
                    v-if="address.deliveryNotes"
                    class="text-muted-foreground mt-2"
                >
                    {{ address.deliveryNotes }}
                </p>
            </div>
        </div>

        <div
            v-if="addressId !== null"
            class="border-rule flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-4"
        >
            <Form
                v-if="!address.isDefault"
                v-bind="defaultMethod.form(addressId)"
                :options="{ preserveScroll: true, preserveState: true }"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="text-electric hover:border-electric focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-sm border-b border-transparent text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
                >
                    <Check class="size-4" aria-hidden="true" />
                    Make default
                </button>
            </Form>

            <button
                type="button"
                :aria-expanded="editing"
                :aria-controls="editorId"
                class="text-muted-foreground hover:text-ink focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-sm text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                @click="editing = !editing"
            >
                <component
                    :is="editing ? X : Pencil"
                    class="size-4"
                    aria-hidden="true"
                />
                {{ editing ? 'Cancel edit' : 'Edit' }}
                <span class="sr-only">{{ address.fullName }}</span>
            </button>

            <button
                v-if="!confirmingRemoval"
                type="button"
                class="text-muted-foreground hover:text-destructive focus-visible:outline-electric ml-auto inline-flex items-center gap-1.5 rounded-sm text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                @click="confirmingRemoval = true"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                Remove
                <span class="sr-only">{{ address.fullName }}</span>
            </button>

            <div
                v-else
                role="alertdialog"
                :aria-label="`Remove ${address.fullName}?`"
                class="border-destructive/40 bg-destructive/5 ml-auto flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border px-3 py-2"
            >
                <p class="text-foreground text-sm">Remove this address?</p>

                <Form
                    v-bind="destroy.form(addressId)"
                    :options="{ preserveScroll: true, preserveState: true }"
                    v-slot="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="bg-destructive font-display focus-visible:outline-electric rounded-lg px-3 py-1.5 text-xs font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                    >
                        Yes, remove
                    </button>
                </Form>

                <button
                    type="button"
                    class="text-muted-foreground hover:text-ink focus-visible:outline-electric rounded-sm text-xs font-medium underline underline-offset-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    @click="confirmingRemoval = false"
                >
                    Keep it
                </button>
            </div>
        </div>

        <Form
            v-if="editing && addressId !== null"
            :id="editorId"
            v-bind="update.form(addressId)"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ errors, processing }"
            class="border-rule flex flex-col gap-5 rounded-lg border border-dashed p-4"
            @success="editing = false"
        >
            <CheckoutAddressFields :errors="errors" :address="address" />

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    :disabled="processing"
                    class="bg-ink font-display focus-visible:outline-electric h-10 rounded-lg px-5 text-sm font-bold tracking-wide text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                >
                    Save changes
                </button>
                <button
                    type="button"
                    class="text-muted-foreground hover:text-ink focus-visible:outline-electric rounded-sm text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                    @click="editing = false"
                >
                    Cancel
                </button>
            </div>
        </Form>
    </li>
</template>
