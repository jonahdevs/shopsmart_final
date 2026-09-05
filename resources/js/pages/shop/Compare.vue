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
 */
const { compare } = defineProps<{ compare: App.Data.CompareData }>();

const isFull = computed(() => compare.products.length >= compare.limit);
</script>

<template>
    <Head title="Compare products" />

    <div class="container py-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="bg-electric block h-0.5 w-8" aria-hidden="true" />
                <h1
                    class="font-display text-foreground mt-3 text-2xl font-black tracking-[-0.035em] uppercase sm:text-4xl"
                >
                    Compare
                </h1>
                <p class="text-muted-foreground mt-2 text-sm tabular-nums">
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
                    class="text-muted-foreground hover:text-destructive focus-visible:outline-electric rounded-xs text-sm underline underline-offset-4 transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
                >
                    Clear the comparison
                </button>
            </Form>
        </div>

        <SavedEmptyState
            v-if="compare.products.length === 0"
            class="mt-10"
            list="compare"
        />

        <div v-else class="mt-10 flex flex-col gap-6">
            <p
                v-if="isFull"
                class="bg-accent text-accent-foreground flex items-start gap-2 rounded-xs px-4 py-3 text-sm"
            >
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                    You are comparing the maximum of
                    {{ compare.limit }} products. Adding another drops the one
                    you added first &mdash; remove a column to choose which
                    goes.
                </span>
            </p>

            <CompareTable
                :products="compare.products"
                :attributes="compare.attributes"
            />

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
