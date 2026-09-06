<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandingSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/BrandingSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import { branding as brandingRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Branding', href: brandingRoute() }],
    },
});

defineProps<{
    branding: {
        store_name: string;
        tagline: string;
        logo_path: string | null;
        favicon_path: string | null;
    };
    social: {
        og_image_path: string | null;
        twitter_handle: string;
        facebook_url: string;
        instagram_url: string;
        x_url: string;
        linkedin_url: string;
        youtube_url: string;
        whatsapp_number: string;
        whatsapp_order_enabled: boolean;
    };
}>();

/** Rendered as one row each; the footer only shows the ones filled in. */
const profiles = [
    { name: 'facebook_url', label: 'Facebook' },
    { name: 'instagram_url', label: 'Instagram' },
    { name: 'x_url', label: 'X' },
    { name: 'linkedin_url', label: 'LinkedIn' },
    { name: 'youtube_url', label: 'YouTube' },
] as const;
</script>

<template>
    <div>
        <Head title="Branding settings" />

        <SettingsForm
            :action="BrandingSettingsController.update.form()"
            title="Branding"
            description="What the store is called, and where else it can be found."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Identity"
                description="Used in the header, in transactional email and in browser chrome."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="store_name"
                        label="Store name"
                        :error="errors.store_name"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="store_name"
                            :default-value="branding.store_name"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="tagline"
                        label="Tagline"
                        :error="errors.tagline"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="tagline"
                            :default-value="branding.tagline"
                        />
                    </SettingsField>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="logo_path"
                        label="Logo path"
                        hint="Path to an uploaded file. Leave blank to use the wordmark."
                        :error="errors.logo_path"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="logo_path"
                            :default-value="branding.logo_path ?? ''"
                        />
                    </SettingsField>

                    <SettingsField
                        name="favicon_path"
                        label="Favicon path"
                        :error="errors.favicon_path"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="favicon_path"
                            :default-value="branding.favicon_path ?? ''"
                        />
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Social profiles"
                description="Only the profiles you fill in appear in the footer, so a blank field leaves no dead icon behind."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        v-for="profile in profiles"
                        :key="profile.name"
                        :name="profile.name"
                        :label="profile.label"
                        :error="errors[profile.name]"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="url"
                            :name="profile.name"
                            :default-value="social[profile.name]"
                            placeholder="https://"
                        />
                    </SettingsField>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="twitter_handle"
                        label="X / Twitter handle"
                        hint="Without the @."
                        :error="errors.twitter_handle"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="twitter_handle"
                            :default-value="social.twitter_handle"
                        />
                    </SettingsField>

                    <SettingsField
                        name="og_image_path"
                        label="Share image path"
                        hint="Used when a page is shared on social media."
                        :error="errors.og_image_path"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="og_image_path"
                            :default-value="social.og_image_path ?? ''"
                        />
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="WhatsApp"
                description="An alternative ordering channel for shoppers who would rather message than check out."
            >
                <SettingsField
                    name="whatsapp_number"
                    label="WhatsApp number"
                    hint="In international format, e.g. +254700000000."
                    :error="errors.whatsapp_number"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="whatsapp_number"
                        :default-value="social.whatsapp_number"
                    />
                </SettingsField>

                <SettingsToggle
                    name="whatsapp_order_enabled"
                    label="Offer WhatsApp ordering"
                    description="Shows a message-to-order button on product pages."
                    :checked="social.whatsapp_order_enabled"
                    :error="errors.whatsapp_order_enabled"
                />
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
