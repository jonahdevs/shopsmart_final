import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AccountLayout from '@/layouts/account/AccountLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsShell from '@/layouts/settings/SettingsShell.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('shop/'):
                return StorefrontLayout;
            case name.startsWith('account/'):
                return [StorefrontLayout, AccountLayout];
            case name.startsWith('admin/'):
                return AppLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            /*
              One component, not a pair: the settings pages are shared by staff
              and customers, and SettingsShell picks the chrome from
              `auth.isStaff` so neither audience ends up in the other's shell.
            */
            case name.startsWith('settings/'):
                return SettingsShell;
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
