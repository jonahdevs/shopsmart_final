<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Tag, X } from '@lucide/vue';
import { useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { destroy, store } from '@/routes/checkout/coupon';

/**
 * The discount code, applied against the session rather than the page.
 *
 * Only the code is held; what it is worth is recomputed with the quote on every
 * render, so this form never carries a figure of its own. Which of the two
 * forms is shown is decided by the code the server sent back on the totals —
 * there is no local "applied" flag to fall out of step with it.
 *
 * Both posts preserve state, because this form sits beside the address panel
 * the shopper may have half filled in and a plain post would remount the page
 * out from under it.
 */
defineProps<{ couponCode: string | null }>();

const id = useId();
</script>

<template>
    <div>
        <Form
            v-if="couponCode === null"
            v-bind="store.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-2"
        >
            <label
                :for="`${id}-code`"
                class="text-muted-foreground flex items-center gap-2 text-xs"
            >
                <Tag class="size-3.5" aria-hidden="true" />
                Discount code
            </label>

            <div class="flex gap-2">
                <input
                    :id="`${id}-code`"
                    name="code"
                    type="text"
                    maxlength="64"
                    autocomplete="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    placeholder="Enter a code"
                    class="border-rule text-foreground placeholder:text-muted-foreground focus-visible:outline-electric h-10 min-w-0 flex-1 rounded-xs border bg-white px-3 text-sm uppercase focus-visible:outline-2 focus-visible:-outline-offset-2"
                />
                <button
                    type="submit"
                    :disabled="processing"
                    class="border-ink hover:bg-ink font-display focus-visible:outline-electric text-foreground h-10 shrink-0 rounded-xs border px-4 text-sm font-bold tracking-wide transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                >
                    Apply
                </button>
            </div>

            <InputError :message="errors.code" />
        </Form>

        <Form
            v-else
            v-bind="destroy.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ processing }"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-foreground flex items-center gap-2 text-sm">
                <Tag class="size-3.5 shrink-0" aria-hidden="true" />
                <span
                    class="font-display text-sm font-bold tracking-[0.08em] uppercase"
                >
                    {{ couponCode }}
                </span>
                <span class="text-muted-foreground text-xs">applied</span>
            </p>

            <button
                type="submit"
                :disabled="processing"
                class="text-muted-foreground hover:text-destructive focus-visible:outline-electric inline-flex items-center gap-1.5 rounded-xs text-xs transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 disabled:opacity-50"
            >
                <X class="size-3.5" aria-hidden="true" />
                Remove
                <span class="sr-only">the discount code {{ couponCode }}</span>
            </button>
        </Form>
    </div>
</template>
