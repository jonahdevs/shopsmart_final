<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MaintenanceSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/MaintenanceSettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminSettings,
    maintenance as maintenanceRoute,
} from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Settings', href: adminSettings().url },
            { title: 'Maintenance', href: maintenanceRoute().url },
        ],
    },
});

defineProps<{
    maintenance: {
        maintenance_mode: boolean;
        maintenance_message: string;
    };
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Maintenance settings" />

        <SettingsForm
            :action="MaintenanceSettingsController.update.form()"
            title="Maintenance"
            description="Close the shop floor to shoppers without closing the admin panel."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Shop floor"
                description="Staff keep browsing the storefront while it is closed, so a change can be checked before anyone else sees it. Sign-in, the account pages and this panel stay open, and the payment gateway can still report on orders already taken."
            >
                <SettingsToggle
                    name="maintenance_mode"
                    label="Close the shop"
                    description="Shoppers get a 503 page carrying the message below. Nothing in a cart is lost."
                    :checked="maintenance.maintenance_mode"
                    :error="errors.maintenance_mode"
                />

                <SettingsField
                    name="maintenance_message"
                    label="Message to shoppers"
                    hint="Shown on the closed-shop page. Saying when you expect to reopen is the most useful thing it can carry."
                    :error="errors.maintenance_message"
                    v-slot="{ id }"
                >
                    <Textarea
                        :id="id"
                        name="maintenance_message"
                        rows="3"
                        :default-value="maintenance.maintenance_message"
                        required
                    />
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
