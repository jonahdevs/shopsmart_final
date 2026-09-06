<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CatalogSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/CatalogSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { catalog as catalogRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Catalog', href: catalogRoute() }],
    },
});

defineProps<{
    inventory: {
        track_stock_by_default: boolean;
        low_stock_threshold: number;
        out_of_stock_behavior: string;
        allow_backorders_by_default: boolean;
    };
    reviews: {
        reviews_enabled: boolean;
        require_verified_purchase: boolean;
        auto_approve: boolean;
        author_display_format: string;
    };
    authorFormats: { value: string; label: string }[];
}>();
</script>

<template>
    <div>
        <Head title="Catalog settings" />

        <SettingsForm
            :action="CatalogSettingsController.update.form()"
            title="Catalog"
            description="Stock defaults for new products, and what customers may say about them."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Inventory"
                description="Applied to newly created products, and to how the storefront reacts when something runs out."
            >
                <SettingsToggle
                    name="track_stock_by_default"
                    label="Track stock on new products"
                    :checked="inventory.track_stock_by_default"
                    :error="errors.track_stock_by_default"
                />

                <SettingsToggle
                    name="allow_backorders_by_default"
                    label="Allow backorders on new products"
                    description="Lets a shopper buy something that is out of stock."
                    :checked="inventory.allow_backorders_by_default"
                    :error="errors.allow_backorders_by_default"
                />

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="low_stock_threshold"
                        label="Low stock threshold"
                        hint="Products at or below this count are flagged on the dashboard."
                        :error="errors.low_stock_threshold"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="low_stock_threshold"
                            :default-value="inventory.low_stock_threshold"
                            min="0"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="out_of_stock_behavior"
                        label="When a product is out of stock"
                        :error="errors.out_of_stock_behavior"
                        v-slot="{ id }"
                    >
                        <NativeSelect
                            :id="id"
                            name="out_of_stock_behavior"
                            :model-value="inventory.out_of_stock_behavior"
                            class="w-full"
                        >
                            <NativeSelectOption value="show">
                                Keep it listed
                            </NativeSelectOption>
                            <NativeSelectOption value="show_unavailable">
                                List it, marked unavailable
                            </NativeSelectOption>
                            <NativeSelectOption value="hide">
                                Hide it from the catalog
                            </NativeSelectOption>
                        </NativeSelect>
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Reviews"
                description="Who may leave a review, whether it publishes on its own, and how much of the reviewer's name is printed."
            >
                <SettingsToggle
                    name="reviews_enabled"
                    label="Collect product reviews"
                    :checked="reviews.reviews_enabled"
                    :error="errors.reviews_enabled"
                />

                <SettingsToggle
                    name="require_verified_purchase"
                    label="Require a delivered order"
                    description="Only customers who received the product can review it."
                    :checked="reviews.require_verified_purchase"
                    :error="errors.require_verified_purchase"
                />

                <SettingsToggle
                    name="auto_approve"
                    label="Publish without moderation"
                    description="Reviews go straight onto the product page instead of into the queue."
                    :checked="reviews.auto_approve"
                    :error="errors.auto_approve"
                />

                <SettingsField
                    name="author_display_format"
                    label="Show the reviewer as"
                    hint="A reviewer's name is kept with the review and stays published after they delete their account, so this decides how much of it the public sees."
                    :error="errors.author_display_format"
                    v-slot="{ id }"
                >
                    <NativeSelect
                        :id="id"
                        name="author_display_format"
                        :model-value="reviews.author_display_format"
                        class="w-full sm:w-96"
                    >
                        <NativeSelectOption
                            v-for="format in authorFormats"
                            :key="format.value"
                            :value="format.value"
                        >
                            {{ format.label }}
                        </NativeSelectOption>
                    </NativeSelect>
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
