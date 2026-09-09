import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    AppWindow,
    Archive,
    Banknote,
    Boxes,
    DatabaseZap,
    CircleUser,
    CreditCard,
    IdCard,
    LayoutGrid,
    Lock,
    Wrench,
    Search,
    Server,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Store,
    SlidersHorizontal,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editAppearance } from '@/routes/appearance';
import {
    backup as settingsBackup,
    branding as settingsBranding,
    business as settingsBusiness,
    cache as settingsCache,
    catalog as settingsCatalog,
    checkout as settingsCheckout,
    maintenance as settingsMaintenance,
    privacy as settingsPrivacy,
    security as settingsSecurity,
    seo as settingsSeo,
    shipping as settingsShipping,
} from '@/routes/admin/settings';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

export type AdminSettingsScreen = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

export type AdminSettingsGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    /** Omitted where every staff member may reach the group. */
    permissions?: string[];
    screens: AdminSettingsScreen[];
};

/**
 * The settings taxonomy, taken from the wryterscript and new-ecommerce builds.
 *
 * The six tabs and their icons are the same in both, so they are the same here:
 * General, Website, App, System, Financial, Other. The grouping has been worked
 * out twice already, and a settings area that will grow past a dozen screens
 * needs buckets decided before the screens exist rather than after.
 *
 * General is the signed-in staff member's own account, not the store's — that
 * is what both references put there, and it is why the account screens are
 * reached through this tab strip rather than through a second nav of their own.
 * Everything below it is the store, and needs `settings.manage`.
 *
 * A tab with no screens, or none this staff member may reach, is not rendered.
 * Every tab has screens behind it now; the filter stays because a role that may
 * not manage settings still reaches General, and because the next screen to be
 * added should have somewhere obvious to land.
 *
 * This is the single source of truth for three surfaces: the rail's Settings
 * disclosure lists the groups, {@see AdminSettingsTabs} repeats them across the
 * top of the page, and {@see AdminSettingsSubnav} lists the screens inside
 * whichever one is showing.
 */
export const settingsGroups: AdminSettingsGroup[] = [
    {
        /* The person, not the shop. Open to any staff member with a login. */
        key: 'general',
        label: 'General',
        icon: Store,
        screens: [
            { title: 'Profile', href: editProfile(), icon: CircleUser },
            { title: 'Security', href: editSecurity(), icon: Lock },
            {
                title: 'Appearance',
                href: editAppearance(),
                icon: SlidersHorizontal,
            },
        ],
    },
    {
        /* The public site: who trades, how it looks, how it is listed. */
        key: 'website',
        label: 'Website',
        icon: AppWindow,
        permissions: ['settings.manage'],
        screens: [
            { title: 'Business', href: settingsBusiness(), icon: IdCard },
            { title: 'Branding', href: settingsBranding(), icon: Store },
            { title: 'SEO', href: settingsSeo(), icon: Search },
            { title: 'Privacy', href: settingsPrivacy(), icon: ShieldCheck },
        ],
    },
    {
        /* How the shop behaves while it is trading. */
        key: 'app',
        label: 'App',
        icon: LayoutGrid,
        permissions: ['settings.manage'],
        screens: [
            { title: 'Catalog', href: settingsCatalog(), icon: Boxes },
            { title: 'Checkout', href: settingsCheckout(), icon: CreditCard },
        ],
    },
    {
        /* The installation rather than the shop: how it runs, and whether. */
        key: 'system',
        label: 'System',
        icon: Server,
        permissions: ['settings.manage'],
        screens: [
            {
                title: 'Security',
                href: settingsSecurity(),
                icon: ShieldAlert,
            },
            {
                title: 'Maintenance',
                href: settingsMaintenance(),
                icon: Wrench,
            },
        ],
    },
    {
        /* What money an order gains on its way to a total. */
        key: 'financial',
        label: 'Financial',
        icon: Banknote,
        permissions: ['settings.manage'],
        screens: [
            { title: 'Shipping & tax', href: settingsShipping(), icon: Truck },
        ],
    },
    {
        /* Housekeeping. Nothing here is a setting; they are all actions. */
        key: 'other',
        label: 'Other',
        icon: Settings,
        permissions: ['settings.manage'],
        screens: [
            { title: 'Backup', href: settingsBackup(), icon: Archive },
            { title: 'Cache', href: settingsCache(), icon: DatabaseZap },
        ],
    },
];

/** The groups the rail lists, before this staff member's permissions apply. */
export const populatedSettingsGroups: AdminSettingsGroup[] =
    settingsGroups.filter((group) => group.screens.length > 0);

export type UseSettingsNavReturn = {
    /** Groups with screens behind them that this staff member may reach. */
    groups: ComputedRef<AdminSettingsGroup[]>;
    /** The group owning the screen showing, or null on a URL none claims. */
    activeGroup: ComputedRef<AdminSettingsGroup | null>;
    /** The screen showing, or null. */
    activeScreen: ComputedRef<AdminSettingsScreen | null>;
    isCurrentUrl: ReturnType<typeof useCurrentUrl>['isCurrentUrl'];
};

/**
 * The settings navigation as this staff member sees it.
 *
 * Both nav components and the account settings layout read from here, so the
 * permission filter and the "which one is showing" search are written once.
 * Hiding a tab is a courtesy; the `can:` middleware on the routes is what
 * actually refuses a request.
 */
export function useSettingsNav(): UseSettingsNavReturn {
    const { canAny } = usePermissions();
    const { isCurrentUrl } = useCurrentUrl();

    const groups = computed(() =>
        populatedSettingsGroups.filter(
            (group) =>
                !group.permissions?.length || canAny(...group.permissions),
        ),
    );

    const activeGroup = computed(
        () =>
            groups.value.find((group) =>
                group.screens.some((screen) => isCurrentUrl(screen.href)),
            ) ?? null,
    );

    const activeScreen = computed(
        () =>
            activeGroup.value?.screens.find((screen) =>
                isCurrentUrl(screen.href),
            ) ?? null,
    );

    return { groups, activeGroup, activeScreen, isCurrentUrl };
}
