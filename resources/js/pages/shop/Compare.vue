<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import CompareTable from '@/components/storefront/CompareTable.vue';
import SavedEmptyState from '@/components/storefront/SavedEmptyState.vue';
import { clear } from '@/routes/compare';

/**
 * The compare tray.
 *
 * The cap is the server's, and it drops the oldest entry rather than refusing a
 * new one — so a full tray is stated as a fact rather than presented as an
 * error, and nothing here tries to block an add.
 *
 * The matrix is wrapped in the storefront's card, which is what gives the
 * table its edge: the scrolling happens in `Table`'s own overflow container
 * inside that card, so the page body never moves sideways.
 */
const { compare } = defineProps<{ compare: App.Data.CompareData }>();

const isFull = computed(() => compare.products.length >= compare.limit);
</script>

<template>
    <Head title="Compare products" />

    <div class="container py-10">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
            <div>
                <p
                    class="text-electric font-display text-[0.625rem] font-bold tracking-[0.18em] uppercase"
                >
                    Side by side
                </p>
                <h1
                    class="font-display text-ink mt-0.5 text-3xl font-extrabold tracking-[-0.03em] sm:text-4xl"
                >
                    Compare
                </h1>
                <p class="text-muted-foreground mt-1 text-sm tabular-nums">
                    <template v-if="compare.products.length === 0">
                        Nothing to compare yet.
                    </template>
                    <template v-else>
                        {{ compare.products.length }} of
                        {{ compare.limit }} slots used
                    </template>
                </p>
            </div>

            <Form
                v-if="compare.products.length > 0"
                v-bind="clear.form()"
                v-slot="{ processing }"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="border-rule text-muted-foreground hover:border-destructive hover:text-destructive focus-visible:outline-electric rounded-lg border bg-white px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                >
                    Clear the comparison
                </button>
            </Form>
        </div>

        <SavedEmptyState
            v-if="compare.products.length === 0"
            class="mt-8"
            list="compare"
        />

        <div v-else class="mt-8 flex flex-col gap-4">
            <p
                v-if="isFull"
                class="bg-tint border-rule text-ink flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm"
            >
                <Info
                    class="text-electric mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>
                    You are comparing the maximum of
                    {{ compare.limit }} products. Adding another drops the one
                    you added first &mdash; remove a column to choose which
                    goes.
                </span>
            </p>

            <div
                class="border-rule shadow-card overflow-hidden rounded-lg border bg-white"
            >
                <CompareTable
                    :products="compare.products"
                    :attributes="compare.attributes"
                />
            </div>

            <p
                v-if="compare.attributes.length === 0"
                class="text-muted-foreground text-sm"
            >
                These products do not list any specifications in common yet, so
                there is nothing to line up below them.
            </p>
        </div>
    </div>
</template>
