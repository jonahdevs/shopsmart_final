<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bookmark,
    Boxes,
    CreditCard,
    FolderTree,
    LayoutGrid,
    Package,
    ScrollText,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Star,
    Store,
    Tag,
    UserRound,
    Users,
} from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import AdminBrand from '@/layouts/admin/AdminBrand.vue';
import { home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminActivity } from '@/routes/admin/activity';
import { index as adminAttributes } from '@/routes/admin/attributes';
import { index as adminBrands } from '@/routes/admin/brands';
import { index as adminCategories } from '@/routes/admin/categories';
import { index as adminCoupons } from '@/routes/admin/coupons';
import { index as adminCustomers } from '@/routes/admin/customers';
import { index as adminOrders } from '@/routes/admin/orders';
import { index as adminPayments } from '@/routes/admin/payments';
import { index as adminProducts } from '@/routes/admin/products';
import { index as adminReviews } from '@/routes/admin/reviews';
import { index as adminRoles } from '@/routes/admin/roles';
import {
    branding as settingsBranding,
    business as settingsBusiness,
    catalog as settingsCatalog,
    checkout as settingsCheckout,
    privacy as settingsPrivacy,
    seo as settingsSeo,
    shipping as settingsShipping,
} from '@/routes/admin/settings';
import { index as adminStaff } from '@/routes/admin/staff';
import type { AdminNavGroup, NavItem } from '@/types';

/**
 * The staff shell's navigation.
 *
 * Each item declares the permissions that admit a staff member to it, and
 * AdminNav drops the ones they do not hold — so a Support role sees Orders and
 * Payments and simply never learns the catalog pages exist. The permissions
 * here must match the `can:` middleware on the corresponding routes in
 * routes/admin.php; that middleware is what actually refuses a request.
 */
const navGroups: AdminNavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: adminDashboard(),
                icon: LayoutGrid,
                exact: true,
            },
        ],
    },
    {
        label: 'Sales',
        items: [
            {
                title: 'Orders',
                href: adminOrders(),
                icon: Package,
                permissions: ['orders.view', 'orders.manage'],
            },
            {
                title: 'Payments',
                href: adminPayments(),
                icon: CreditCard,
                permissions: ['payments.view', 'payments.manage'],
            },
            {
                title: 'Customers',
                href: adminCustomers(),
                icon: UserRound,
                permissions: ['customers.view', 'customers.manage'],
            },
        ],
    },
    {
        label: 'Catalog',
        items: [
            {
                title: 'Products',
                href: adminProducts(),
                icon: Boxes,
                permissions: ['products.view', 'products.manage'],
            },
            {
                title: 'Categories',
                href: adminCategories(),
                icon: FolderTree,
                permissions: ['catalog.manage'],
            },
            {
                title: 'Brands',
                href: adminBrands(),
                icon: Bookmark,
                permissions: ['catalog.manage'],
            },
            {
                title: 'Attributes',
                href: adminAttributes(),
                icon: SlidersHorizontal,
                permissions: ['catalog.manage'],
            },
        ],
    },
    {
        label: 'Marketing',
        items: [
            {
                title: 'Reviews',
                href: adminReviews(),
                icon: Star,
                permissions: ['reviews.manage'],
            },
            {
                title: 'Coupons',
                href: adminCoupons(),
                icon: Tag,
                permissions: ['marketing.manage'],
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                /*
                  A disclosure, not a link. `/admin/settings` only redirects to
                  the first screen, so listing it as a single destination left
                  the other six reachable by typing the URL and no other way.
                */
                title: 'Settings',
                href: settingsBusiness(),
                icon: Settings,
                permissions: ['settings.manage'],
                children: [
                    {
                        title: 'Business',
                        href: settingsBusiness(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'Branding',
                        href: settingsBranding(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'Catalog',
                        href: settingsCatalog(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'Checkout',
                        href: settingsCheckout(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'Shipping & tax',
                        href: settingsShipping(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'SEO',
                        href: settingsSeo(),
                        permissions: ['settings.manage'],
                    },
                    {
                        title: 'Privacy',
                        href: settingsPrivacy(),
                        permissions: ['settings.manage'],
                    },
                ],
            },
            {
                title: 'Staff',
                href: adminStaff(),
                icon: Users,
                permissions: ['staff.manage'],
            },
            {
                // Super Admin only — every other seeded role is refused
                // `roles.manage`, so this is the one item most staff never see.
                title: 'Roles',
                href: adminRoles(),
                icon: ShieldCheck,
                permissions: ['roles.manage'],
            },
            {
                title: 'Activity log',
                href: adminActivity(),
                icon: ScrollText,
                permissions: ['activity.view'],
            },
        ],
    },
];

/**
 * A way back to the shop floor. Staff cannot buy — EnsureUserIsCustomer sees to
 * that — but they do need to look at what a customer sees.
 */
const footerNavItems: NavItem[] = [
    {
        title: 'View storefront',
        href: home(),
        icon: Store,
    },
];
</script>

<template>
    <!--
      `offcanvas`, not `icon`: the rail carries a two-level disclosure, and an
      icon-only rail has nowhere to put a child list. Desktop keeps it open and
      the toggle hides it outright; mobile gets the sheet.
    -->
    <Sidebar collapsible="offcanvas" variant="sidebar">
        <SidebarHeader class="border-sidebar-border border-b">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="adminDashboard()">
                            <AdminBrand />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4 py-2">
            <AdminNav :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter class="border-sidebar-border border-t">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
