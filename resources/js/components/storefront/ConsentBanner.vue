<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { consentConfig, useConsentPreferences } from '@/lib/consent';
import { store } from '@/routes/consent';

/**
 * The cookie banner.
 *
 * It is a plain fixed band inside StoreShell's `.storefront` wrapper rather
 * than a Dialog: a portalled primitive would escape the brand token remap, and
 * a modal that traps focus before a visitor has read a word of the page is a
 * worse thing to do to them than a dismissible bar.
 *
 * The answer is posted to the server, which is what actually decides whether a
 * tag is written into the next document — see App\Support\Consent. Nothing here
 * loads a tracker, so a visitor who never answers is never measured.
 */
const config = consentConfig();

const { preferencesOpen, closeConsentPreferences } = useConsentPreferences();

/** Set once the visitor has answered in this page's lifetime. */
const answered = ref(false);

/** Whether the per-category detail is showing. */
const detailed = ref(false);

/** A read-only mirror of the ticked boxes, for the "Save choices" submitter. */
const selected = ref(new Set(config.granted));

const visible = computed(
    () =>
        config.categories.length > 0 &&
        ((config.needsAnswer && !answered.value) || preferencesOpen.value),
);

const showDetail = computed(() => detailed.value || preferencesOpen.value);

/**
 * What each button is about to grant, so the reload below only happens when
 * the answer actually changes what the document may load.
 */
const pending = ref<string[]>([]);

function intend(categories: string[]) {
    pending.value = categories;
}

function toggle(category: string, checked: boolean | 'indeterminate') {
    const next = new Set(selected.value);

    if (checked === true) {
        next.add(category);
    } else {
        next.delete(category);
    }

    selected.value = next;
}

function sameAsCurrent(categories: string[]): boolean {
    const current = new Set(config.granted);

    return (
        categories.length === current.size &&
        categories.every((category) => current.has(category))
    );
}

/**
 * Measurement tags live in the document head, written there by the server for
 * the categories this visitor had already granted. A new answer therefore only
 * takes effect on the next full document — so when it changes anything, ask for
 * one. When it does not, the banner simply goes away.
 */
function handleSuccess() {
    answered.value = true;
    closeConsentPreferences();

    if (!sameAsCurrent(pending.value)) {
        window.location.reload();
    }
}
</script>

<template>
    <div
        v-if="visible"
        class="border-electric bg-ink fixed inset-x-0 bottom-0 z-50 border-t-2 text-white"
        role="region"
        aria-label="Cookie preferences"
    >
        <Form
            v-bind="store.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            @success="handleSuccess"
            class="container flex flex-col gap-5 py-6"
        >
            <div class="flex flex-col gap-2">
                <h2
                    class="font-display text-sm font-extrabold tracking-[0.08em] uppercase"
                >
                    Your choice about cookies
                </h2>

                <p class="max-w-3xl text-sm leading-relaxed text-white/65">
                    Signing in, your basket and the checkout need storage to
                    work at all, so those are always on. Everything else is up
                    to you — nothing optional loads until you say so.
                    <a
                        v-if="config.privacyPolicyUrl"
                        :href="config.privacyPolicyUrl"
                        class="focus-visible:outline-electric rounded-sm text-white underline decoration-white/40 underline-offset-4 transition-colors hover:decoration-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        Read the privacy policy
                    </a>
                </p>
            </div>

            <fieldset v-if="showDetail" class="grid gap-4 sm:grid-cols-2">
                <legend class="sr-only">Optional categories</legend>

                <div
                    v-for="category in config.categories"
                    :key="category.value"
                    class="flex items-start gap-3"
                >
                    <Checkbox
                        :id="`consent-choice-${category.value}`"
                        name="categories[]"
                        :value="category.value"
                        :default-value="config.granted.includes(category.value)"
                        class="mt-0.5 border-white/40"
                        @update:model-value="
                            (checked) => toggle(category.value, checked)
                        "
                    />

                    <div class="grid gap-1">
                        <Label
                            :for="`consent-choice-${category.value}`"
                            class="text-sm font-semibold text-white"
                        >
                            {{ category.label }}
                        </Label>
                        <p class="text-xs leading-relaxed text-white/55">
                            {{ category.description }}
                        </p>
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    name="accept"
                    value="all"
                    class="bg-electric focus-visible:outline-electric rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2"
                    @click="
                        intend(config.categories.map((category) => category.value))
                    "
                >
                    Accept all
                </button>

                <button
                    type="submit"
                    name="accept"
                    value="none"
                    class="focus-visible:outline-electric rounded-lg border border-white/25 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:border-white/60 focus-visible:outline-2 focus-visible:outline-offset-2"
                    @click="intend([])"
                >
                    Necessary only
                </button>

                <button
                    v-if="showDetail"
                    type="submit"
                    name="accept"
                    value="selected"
                    class="focus-visible:outline-electric rounded-lg border border-white/25 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:border-white/60 focus-visible:outline-2 focus-visible:outline-offset-2"
                    @click="intend([...selected])"
                >
                    Save my choices
                </button>

                <button
                    v-else
                    type="button"
                    class="focus-visible:outline-electric rounded-sm px-1 text-sm text-white/65 underline decoration-white/30 underline-offset-4 transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2"
                    @click="detailed = true"
                >
                    Choose what to allow
                </button>
            </div>
        </Form>
    </div>
</template>
