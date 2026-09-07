import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AccountLayout from '@/layouts/account/AccountLayout.vue';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
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
                return AdminLayout;
            /*
              The storefront's chrome, not the staff shell: an error page is
              most often reached by a shopper following a dead link, and it has
              to keep the header and footer that offer them a way onward. Staff
              hitting a 403 in the admin panel get the same page, which is a
              fair trade for one component instead of two.
            */
            case name.startsWith('errors/'):
                return StorefrontLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            /*
              One component, not a pair: the settings pages are shared by staff
              and customers, and SettingsShell picks the chrome from
              `auth.isStaff` so neither audience ends up in the other's shell.
            */
            case name.startsWith('settings/'):
                return SettingsShell;
            /*
              Unreachable in practice — every page above is matched by an
              explicit case, and `Welcome` opts out with null. It resolves to
              the staff shell rather than a bare page so that a route added
              without a case here fails visibly in the admin rather than
              rendering unstyled.
            */
            default:
                return AdminLayout;
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
