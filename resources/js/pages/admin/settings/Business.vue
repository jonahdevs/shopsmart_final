<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BusinessSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/BusinessSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { business as businessRoute } from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Business', href: businessRoute() }],
    },
});

defineProps<{
    business: {
        legal_name: string;
        registration_number: string;
        tax_pin: string;
        contact_email: string;
        contact_phone: string;
        address: string;
        business_hours: string;
    };
    localization: {
        currency: string;
        weight_unit: string;
        dimension_unit: string;
        timezone: string;
    };
    currency: {
        symbol: string;
        symbol_position: string;
        decimals: number;
        thousand_separator: string;
        decimal_separator: string;
    };
    timezones: string[];
}>();

const weightUnits = ['g', 'kg', 'lb', 'oz'];
const dimensionUnits = ['mm', 'cm', 'm', 'in'];
</script>

<template>
    <div>
        <Head title="Business settings" />

        <SettingsForm
            :action="BusinessSettingsController.update.form()"
            title="Business"
            description="The legal entity behind the store, the region it trades in, and how it writes money down."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Legal entity"
                description="Shown on invoices and receipts, and used as the contact of record."
            >
                <SettingsField
                    name="legal_name"
                    label="Registered name"
                    :error="errors.legal_name"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="legal_name"
                        :default-value="business.legal_name"
                        required
                    />
                </SettingsField>

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="registration_number"
                        label="Registration number"
                        :error="errors.registration_number"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="registration_number"
                            :default-value="business.registration_number"
                        />
                    </SettingsField>

                    <SettingsField
                        name="tax_pin"
                        label="Tax PIN"
                        hint="Stored encrypted."
                        :error="errors.tax_pin"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="tax_pin"
                            :default-value="business.tax_pin"
                            autocomplete="off"
                        />
                    </SettingsField>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="contact_email"
                        label="Contact email"
                        :error="errors.contact_email"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="email"
                            name="contact_email"
                            :default-value="business.contact_email"
                        />
                    </SettingsField>

                    <SettingsField
                        name="contact_phone"
                        label="Contact phone"
                        :error="errors.contact_phone"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="contact_phone"
                            :default-value="business.contact_phone"
                        />
                    </SettingsField>
                </div>

                <SettingsField
                    name="address"
                    label="Address"
                    :error="errors.address"
                    v-slot="{ id }"
                >
                    <Textarea
                        :id="id"
                        name="address"
                        :default-value="business.address"
                        rows="3"
                    />
                </SettingsField>

                <SettingsField
                    name="business_hours"
                    label="Business hours"
                    hint="Free text, e.g. Mon–Fri 8am–6pm, Sat 9am–2pm."
                    :error="errors.business_hours"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="business_hours"
                        :default-value="business.business_hours"
                    />
                </SettingsField>
            </SettingsSection>

            <SettingsSection
                title="Region"
                description="Defaults applied to new products and to every date the store prints."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="currency"
                        label="Trading currency"
                        hint="Three-letter ISO code, e.g. KES."
                        :error="errors.currency"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="currency"
                            :default-value="localization.currency"
                            maxlength="3"
                            class="uppercase"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="timezone"
                        label="Timezone"
                        :error="errors.timezone"
                        v-slot="{ id }"
                    >
                        <NativeSelect
                            :id="id"
                            name="timezone"
                            :model-value="localization.timezone"
                            class="w-full"
                        >
                            <NativeSelectOption
                                v-for="zone in timezones"
                                :key="zone"
                                :value="zone"
                            >
                                {{ zone }}
                            </NativeSelectOption>
                        </NativeSelect>
                    </SettingsField>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="weight_unit"
                        label="Weight unit"
                        :error="errors.weight_unit"
                        v-slot="{ id }"
                    >
                        <NativeSelect
                            :id="id"
                            name="weight_unit"
                            :model-value="localization.weight_unit"
                            class="w-full"
                        >
                            <NativeSelectOption
                                v-for="unit in weightUnits"
                                :key="unit"
                                :value="unit"
                            >
                                {{ unit }}
                            </NativeSelectOption>
                        </NativeSelect>
                    </SettingsField>

                    <SettingsField
                        name="dimension_unit"
                        label="Dimension unit"
                        :error="errors.dimension_unit"
                        v-slot="{ id }"
                    >
                        <NativeSelect
                            :id="id"
                            name="dimension_unit"
                            :model-value="localization.dimension_unit"
                            class="w-full"
                        >
                            <NativeSelectOption
                                v-for="unit in dimensionUnits"
                                :key="unit"
                                :value="unit"
                            >
                                {{ unit }}
                            </NativeSelectOption>
                        </NativeSelect>
                    </SettingsField>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Money formatting"
                description="How every price on the storefront is written. Amounts themselves are always stored in cents."
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <SettingsField
                        name="symbol"
                        label="Currency symbol"
                        :error="errors.symbol"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="symbol"
                            :default-value="currency.symbol"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="symbol_position"
                        label="Symbol position"
                        :error="errors.symbol_position"
                        v-slot="{ id }"
                    >
                        <NativeSelect
                            :id="id"
                            name="symbol_position"
                            :model-value="currency.symbol_position"
                            class="w-full"
                        >
                            <NativeSelectOption value="before">
                                Before the amount (KES 1,200)
                            </NativeSelectOption>
                            <NativeSelectOption value="after">
                                After the amount (1,200 KES)
                            </NativeSelectOption>
                        </NativeSelect>
                    </SettingsField>
                </div>

                <div class="grid gap-6 sm:grid-cols-3">
                    <SettingsField
                        name="decimals"
                        label="Decimal places"
                        :error="errors.decimals"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            type="number"
                            name="decimals"
                            :default-value="currency.decimals"
                            min="0"
                            max="4"
                            required
                        />
                    </SettingsField>

                    <SettingsField
                        name="thousand_separator"
                        label="Thousands separator"
                        :error="errors.thousand_separator"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="thousand_separator"
                            :default-value="currency.thousand_separator"
                            maxlength="1"
                        />
                    </SettingsField>

                    <SettingsField
                        name="decimal_separator"
                        label="Decimal separator"
                        :error="errors.decimal_separator"
                        v-slot="{ id }"
                    >
                        <Input
                            :id="id"
                            name="decimal_separator"
                            :default-value="currency.decimal_separator"
                            maxlength="1"
                            required
                        />
                    </SettingsField>
                </div>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
