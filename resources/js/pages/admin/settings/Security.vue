<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import SecuritySettingsController from '@/actions/App/Http/Controllers/Admin/Settings/SecuritySettingsController';
import SettingsField from '@/components/admin/settings/SettingsField.vue';
import SettingsForm from '@/components/admin/settings/SettingsForm.vue';
import SettingsSection from '@/components/admin/settings/SettingsSection.vue';
import SettingsToggle from '@/components/admin/settings/SettingsToggle.vue';
import { Input } from '@/components/ui/input';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminSettings,
    security as securityRoute,
} from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Settings', href: adminSettings().url },
            { title: 'Security', href: securityRoute().url },
        ],
    },
});

defineProps<{
    security: {
        require_two_factor: boolean;
        login_attempts_per_minute: number;
    };
    /**
     * Whether the staff member reading this page has an authenticator of their
     * own. Turning the rule on without one locks them out on the next request,
     * which is worth saying before they save rather than after.
     */
    viewerHasTwoFactor: boolean;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Security settings" />

        <SettingsForm
            :action="SecuritySettingsController.update.form()"
            title="Security"
            description="How hard it is to get in, and how hard it is to guess your way in."
            v-slot="{ errors }"
        >
            <SettingsSection
                title="Staff sign-in"
                description="Applies to the admin panel only. Shoppers are never asked for a second factor."
            >
                <SettingsToggle
                    name="require_two_factor"
                    label="Require two-factor authentication for staff"
                    description="A staff member without a confirmed authenticator is sent to their own security page instead of the panel."
                    :checked="security.require_two_factor"
                    :error="errors.require_two_factor"
                />

                <p
                    v-if="!viewerHasTwoFactor"
                    class="border-rule text-muted-foreground flex items-start gap-2 rounded-lg border border-dashed p-3 text-xs"
                >
                    <TriangleAlert
                        class="text-destructive mt-px size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        You have not set up an authenticator yet. Turning this
                        on will send you to your own security page on your next
                        request, and you will not reach the panel again until
                        you have enrolled.
                    </span>
                </p>
            </SettingsSection>

            <SettingsSection
                title="Sign-in throttle"
                description="How many failed attempts one email address and one connection may make in a minute, before both are locked out for the rest of it."
            >
                <SettingsField
                    name="login_attempts_per_minute"
                    label="Attempts per minute"
                    hint="Between 3 and 60. Laravel's own default is 5."
                    :error="errors.login_attempts_per_minute"
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        name="login_attempts_per_minute"
                        type="number"
                        min="3"
                        max="60"
                        class="max-w-32"
                        :default-value="security.login_attempts_per_minute"
                        required
                    />
                </SettingsField>
            </SettingsSection>
        </SettingsForm>
    </div>
</template>
