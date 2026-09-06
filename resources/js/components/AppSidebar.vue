<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CreditCard,
    LayoutGrid,
    Package,
    Store,
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
import { index as adminOrders } from '@/routes/admin/orders';
import { index as adminPayments } from '@/routes/admin/payments';
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
