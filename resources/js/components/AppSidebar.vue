<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bookmark,
    Boxes,
    CreditCard,
    FolderTree,
    LayoutGrid,
    Package,
    Settings,
    SlidersHorizontal,
    Star,
    Store,
    Tag,
    UserRound,
} from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminAttributes } from '@/routes/admin/attributes';
import { index as adminBrands } from '@/routes/admin/brands';
import { index as adminCategories } from '@/routes/admin/categories';
import { index as adminCoupons } from '@/routes/admin/coupons';
import { index as adminCustomers } from '@/routes/admin/customers';
import { index as adminOrders } from '@/routes/admin/orders';
import { index as adminPayments } from '@/routes/admin/payments';
import { index as adminProducts } from '@/routes/admin/products';
import { index as adminReviews } from '@/routes/admin/reviews';
import { index as adminSettings } from '@/routes/admin/settings';
import { home } from '@/routes';
import type { AdminNavGroup, NavItem } from '@/types';

/**
 * The staff shell's navigation.
 *
 * Each item declares the permissions that admit a staff member to it, and
 * AdminNav drops the ones they do not hold — so a Support role sees Orders and
 * Payments and simply never learns the catalog pages exist. The permissions
 * here must match the `can:` middleware on the corresponding routes in
 * routes/admin.php; that middleware is what actually refuses a request.
 *
 * Later phases append their own groups here as their routes land.
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
                // One entry, not seven: the settings section carries its own
                // sub-navigation, and seven store-configuration links in the
                // main rail would outnumber every trading destination above it.
                title: 'Settings',
                href: adminSettings(),
                icon: Settings,
                permissions: ['settings.manage'],
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
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="adminDashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <AdminNav :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
