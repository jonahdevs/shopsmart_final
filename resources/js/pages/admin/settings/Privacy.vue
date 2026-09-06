<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import PrivacySettingsController from '@/actions/App/Http/Controllers/Admin/Settings/PrivacySettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { privacy as privacyRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Privacy', href: privacyRoute() }],
    },
});

const props = defineProps<{
    legal: {
        consent_categories: string[];
        privacy_policy_url: string;
        terms_url: string;
        recently_viewed_retention_days: number;
        activity_log_retention_days: number;
    };
    analytics: {
        ga4_id: string;
        gtm_id: string;
        meta_pixel_id: string;
    };
    consentCategories: { value: string; label: string; description: string }[];
    tagCategories: Record<string, string>;
}>();

/**
 * A read-only mirror of the category checkboxes.
 *
 * The form stays uncontrolled — the checkboxes carry `name` and
 * `:default-value` and submit themselves — but the tag fields below need to say
 * out loud when the category that gates them has just been unticked, and that
 * warning has to react before the form is saved.
 */
const offered = ref(new Set(props.legal.consent_categories));

function toggleCategory(category: string, checked: boolean | 'indeterminate') {
    const next = new Set(offered.value);

    if (checked === true) {
        next.add(category);
    } else {
        next.delete(category);
    }

    offered.value = next;
}

const tags = computed(() =>
    [
        {
            name: 'ga4_id',
            label: 'Google Analytics 4 measurement ID',
            placeholder: 'G-XXXXXXXXXX',
            value: props.analytics.ga4_id,
        },
        {
            name: 'gtm_id',
            label: 'Google Tag Manager container ID',
            placeholder: 'GTM-XXXXXXX',
            value: props.analytics.gtm_id,
        },
        {
            name: 'meta_pixel_id',
            label: 'Meta pixel ID',
            placeholder: '000000000000000',
            value: props.analytics.meta_pixel_id,
        },
    ].map((tag) => {
        const category = props.tagCategories[tag.name] ?? '';

        return {
            ...tag,
            category,
            categoryLabel:
                props.consentCategories.find(
                    (option) => option.value === category,
                )?.label ?? category,
            gated: offered.value.has(category),
        };
    }),
);

const ungatedTags = computed(() => tags.value.filter((tag) => !tag.gated));
</script>

<template>
    <div>
        <Head title="Privacy settings" />

        <SettingsForm
            :action="PrivacySettingsController.update.form()"
            title="Privacy"
            description="What the store asks visitors' permission for, where it explains itself, and how long it keeps what it collects."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Cookie consent"
                description="The categories the banner asks about. Strictly necessary storage — the session, the basket, the CSRF token — is never asked about and cannot be declined."
            >
                <fieldset class="grid gap-4">
                    <legend class="sr-only">Consent categories</legend>

                    <div
                        v-for="category in consentCategories"
                        :key="category.value"
                        class="flex items-start gap-3"
                    >
                        <Checkbox
                            :id="`consent-${category.value}`"
                            name="consent_categories[]"
                            :value="category.value"
                            :default-value="
                                legal.consent_categories.includes(
                                    category.value,
                                )
                            "
                            class="mt-0.5"
                            @update:model-value="
                                (checked) =>
                                    toggleCategory(category.value, checked)
                            "
                        />

                        <div class="grid gap-1">
                            <Label
                                :for="`consent-${category.value}`"
                                class="font-medium"
                            >
                                {{ category.label }}
                            </Label>
                            <p class="text-muted-foreground text-xs">
                                {{ category.description }}
                            </p>
                        </div>
                    </div>
                </fieldset>

                <p class="text-muted-foreground text-xs">
                    Untick everything to remove the banner. Nothing optional can
                    then be granted, so no third-party tag will load for anyone.
                </p>
            </SettingsSection>

            <SettingsSection
                title="Policies"
                description="Linked from the footer and from the consent banner. Either a full address or a path beginning with /."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="privacy_policy_url"
                        label="Privacy policy"
                        :error="errors.privacy_policy_url"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="privacy_policy_url"
                            :default-value="legal.privacy_policy_url"
                            placeholder="/privacy"
                        />
                    </SettingsField>

                    <SettingsField
                        name="terms_url"
                        label="Terms and conditions"
                        :error="errors.terms_url"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="terms_url"
                            :default-value="legal.terms_url"
                            placeholder="/terms"
                        />
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Retention"
                description="Two trails record what a person did rather than what they bought, and both are pruned nightly. Orders are not affected — they are the store's accounting record and keep their customer details."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="recently_viewed_retention_days"
                        label="Browsing history"
                        hint="Days a recently-viewed row survives. Zero keeps it indefinitely."
                        :error="errors.recently_viewed_retention_days"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="recently_viewed_retention_days"
                            :default-value="
                                legal.recently_viewed_retention_days
                            "
                            min="0"
                            max="3650"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="activity_log_retention_days"
                        label="Activity log"
                        hint="Days an admin activity entry survives. Zero keeps it indefinitely."
                        :error="errors.activity_log_retention_days"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="activity_log_retention_days"
                            :default-value="legal.activity_log_retention_days"
                            min="0"
                            max="3650"
                            required
                        />
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Measurement tags"
                description="Each tag is loaded only for a visitor who has granted the category it belongs to. Filling one in does not on its own start tracking anybody."
            >
                <div
                    v-if="ungatedTags.length"
                    class="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-3 rounded-lg border p-4 text-sm"
                >
                    <ShieldAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />

                    <div class="space-y-1">
                        <p class="font-medium">
                            Some tags below can never load.
                        </p>
                        <ul class="list-disc space-y-0.5 pl-4">
                            <li v-for="tag in ungatedTags" :key="tag.name">
                                {{ tag.label }} needs
                                {{ tag.categoryLabel }} consent, and the banner
                                does not offer that category.
                            </li>
                        </ul>
                    </div>
                </div>

                <SettingsField
                    v-for="tag in tags"
                    :key="tag.name"
                    :name="tag.name"
                    :label="tag.label"
                    :hint="`Loads under ${tag.categoryLabel} consent.`"
                    :error="errors[tag.name]"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        :name="tag.name"
                        :default-value="tag.value"
                        :placeholder="tag.placeholder"
                        class="sm:w-96"
                    />
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
