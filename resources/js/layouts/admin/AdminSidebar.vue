<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bookmark,
    Boxes,
    CreditCard,
    FolderTree,
    KeyRound,
    LayoutGrid,
    Package,
    Percent,
    ScrollText,
    Settings,
    SlidersHorizontal,
    Star,
    Tag,
    Tags,
    UserRound,
    Users,
} from '@lucide/vue';
import AdminNav from '@/components/admin/AdminNav.vue';
import { populatedSettingsGroups } from '@/components/admin/settings/settingsNav';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import AdminBrand from '@/layouts/admin/AdminBrand.vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminActivity } from '@/routes/admin/activity';
import { index as adminAttributes } from '@/routes/admin/attributes';
import { index as adminBrands } from '@/routes/admin/brands';
import { index as adminCategories } from '@/routes/admin/categories';
import { index as adminCoupons } from '@/routes/admin/coupons';
import { index as adminCustomers } from '@/routes/admin/customers';
import { index as adminOrders } from '@/routes/admin/orders';
import { index as adminPermissions } from '@/routes/admin/permissions';
import { index as adminPayments } from '@/routes/admin/payments';
import { index as adminProducts } from '@/routes/admin/products';
import { index as adminReviews } from '@/routes/admin/reviews';
import { index as adminRoles } from '@/routes/admin/roles';
import { index as adminTags } from '@/routes/admin/tags';
import { index as adminTaxClasses } from '@/routes/admin/tax-classes';
import type { AdminNavGroup } from '@/types';

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
            {
                // Between the structure and the rates, which is where the
                // reference build keeps it. A tag is filing, like a category or
                // a brand — but unlike them it is what the home page rails are
                // actually built from, so it belongs in this group rather than
                // under Marketing beside the coupons.
                title: 'Tags',
                href: adminTags(),
                icon: Tags,
                permissions: ['catalog.manage'],
            },
            {
                title: 'Tax classes',
                href: adminTaxClasses(),
                icon: Percent,
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
    /*
      Who works here, what they may do, and what they did — the same three
      questions routes/admin/access.php is split along, and the group the
      new-ecommerce build keeps them in. They are deliberately not under System
      with the settings: a settings screen changes how the shop behaves, and
      these change who is allowed to change it.
    */
    {
        label: 'Access',
        items: [
            {
                // One screen. A role is a definition and a staff member is an
                // instance of it, and separating them meant the question every
                // reader arrives with — who would this affect? — needed two
                // pages. Guarded by `staff.manage`; the role cards on it are
                // hidden from anyone without `roles.manage`.
                title: 'Staff and roles',
                href: adminRoles(),
                icon: Users,
                permissions: ['staff.manage'],
            },
            {
                title: 'Permissions',
                href: adminPermissions(),
                icon: KeyRound,
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
    {
        label: 'System',
        items: [
            {
                /*
                  A disclosure, not a link. `/admin/settings` only redirects to
                  the first screen, so listing it as a single destination left
                  the other six reachable by typing the URL and no other way.

                  The children are the groups, not the screens: a group opens
                  onto its first screen and AdminSettingsTabs offers the rest
                  across the top of the page, which keeps the rail one section
                  deep instead of listing more settings than there are trading
                  destinations above them.

                  The parent carries no permission of its own because General is
                  the staff member's own account, which everyone with a login
                  may reach. Each child carries the group's, so a role without
                  `settings.manage` opens Settings onto General alone.
                */
                title: 'Settings',
                href: populatedSettingsGroups[0].screens[0].href,
                icon: Settings,
                children: populatedSettingsGroups.map((group) => ({
                    title: group.label,
                    href: group.screens[0].href,
                    matches: group.screens.map((screen) => screen.href),
                    permissions: group.permissions,
                })),
            },
        ],
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
    </Sidebar>
</template>
