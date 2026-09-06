<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ShippingSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/ShippingSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { shipping as shippingRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Shipping & tax', href: shippingRoute() }],
    },
});

defineProps<{
    shipping: {
        local_pickup_enabled: boolean;
        pickup_address: string;
        flat_rate: number;
        free_shipping_threshold: number;
    };
    tax: {
        tax_enabled: boolean;
        default_tax_class_id: number | null;
        prices_include_tax: boolean;
    };
    taxClasses: { value: number; label: string }[];
    currencySymbol: string;
}>();
</script>

<template>
    <div>
        <Head title="Shipping and tax settings" />

        <SettingsForm
            :action="ShippingSettingsController.update.form()"
            title="Shipping &amp; tax"
            description="What delivery costs, and what tax is added on top."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Delivery"
                description="One flat rate, waived once the order is large enough. Pickup is always free and carries no rate of its own."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="flat_rate"
                        label="Flat delivery rate"
                        :hint="`In whole ${currencySymbol}, charged on every delivery order below the threshold.`"
                        :error="errors.flat_rate"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="flat_rate"
                            :default-value="shipping.flat_rate"
                            min="0"
                            step="0.01"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="free_shipping_threshold"
                        label="Free delivery from"
                        :hint="`In whole ${currencySymbol}, measured on the subtotal after any coupon.`"
                        :error="errors.free_shipping_threshold"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="free_shipping_threshold"
                            :default-value="shipping.free_shipping_threshold"
                            min="0"
                            step="0.01"
                            required
                        />
                    </SettingsField>
                </div>

                <SettingsToggle
                    name="local_pickup_enabled"
                    label="Offer local pickup"
                    :checked="shipping.local_pickup_enabled"
                    :error="errors.local_pickup_enabled"
                />

                <SettingsField
                    name="pickup_address"
                    label="Pickup address"
                    :error="errors.pickup_address"
                    v-slot="{ id }"
                >
                    <Textarea
                        :id="id"
                        name="pickup_address"
                        :default-value="shipping.pickup_address"
                        rows="3"
                    />
                </SettingsField>
            </SettingsSection>

            <SettingsSection
                title="Tax"
                description="Kenyan retail prices are normally quoted VAT-inclusive, so the tax is shown as part of the price rather than added at the end."
            >
                <SettingsToggle
                    name="tax_enabled"
                    label="Calculate tax"
                    :checked="tax.tax_enabled"
                    :error="errors.tax_enabled"
                />

                <SettingsToggle
                    name="prices_include_tax"
                    label="Catalog prices already include tax"
                    :checked="tax.prices_include_tax"
                    :error="errors.prices_include_tax"
                />

                <SettingsField
                    name="default_tax_class_id"
                    label="Default tax class"
                    hint="Used for products that do not name one of their own."
                    :error="errors.default_tax_class_id"
                    v-slot="{ id }"
                >
                    <NativeSelect
                        :id="id"
                        name="default_tax_class_id"
                        :model-value="tax.default_tax_class_id ?? ''"
                        class="w-full sm:w-96"
                    >
                        <NativeSelectOption value="">None</NativeSelectOption>
                        <NativeSelectOption
                            v-for="taxClass in taxClasses"
                            :key="taxClass.value"
                            :value="taxClass.value"
                        >
                            {{ taxClass.label }}
                        </NativeSelectOption>
                    </NativeSelect>
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
