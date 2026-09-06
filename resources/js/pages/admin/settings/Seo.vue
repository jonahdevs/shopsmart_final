<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SeoSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/SeoSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { seo as seoRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'SEO', href: seoRoute() }],
    },
});

defineProps<{
    seo: {
        meta_title_pattern: string;
        default_meta_description: string;
        index_site: boolean;
        generate_sitemap: boolean;
    };
}>();
</script>

<template>
    <div>
        <Head title="SEO settings" />

        <SettingsForm
            :action="SeoSettingsController.update.form()"
            title="SEO"
            description="How the store describes itself to search engines."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Titles and descriptions"
                description="Fallbacks. A page that sets its own title or description wins."
            >
                <SettingsField
                    name="meta_title_pattern"
                    label="Title pattern"
                    hint="{page} is the page's own title, {site} the store name."
                    :error="errors.meta_title_pattern"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="meta_title_pattern"
                        :default-value="seo.meta_title_pattern"
                        required
                    />
                </SettingsField>

                <SettingsField
                    name="default_meta_description"
                    label="Default description"
                    hint="Around 155 characters is what a result page will show."
                    :error="errors.default_meta_description"
                    v-slot="{ id }"
                >
                    <Textarea
                        :id="id"
                        name="default_meta_description"
                        :default-value="seo.default_meta_description"
                        rows="3"
                    />
                </SettingsField>
            </SettingsSection>

            <SettingsSection
                title="Indexing"
                description="Turn indexing off while the store is being set up, and on when it opens."
            >
                <SettingsToggle
                    name="index_site"
                    label="Invite search engines to index the site"
                    :checked="seo.index_site"
                    :error="errors.index_site"
                />

                <SettingsToggle
                    name="generate_sitemap"
                    label="Publish a sitemap"
                    :checked="seo.generate_sitemap"
                    :error="errors.generate_sitemap"
                />
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
