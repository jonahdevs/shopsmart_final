import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/**
 * One destination in the admin sidebar.
 *
 * `permissions` is what the sidebar filters on — the item is shown when the
 * staff member holds ANY of them, matching how the routes are grouped. It is a
 * rendering hint only: the route's own `can:` middleware is what refuses a
 * request, and hiding a link is never what protects a page.
 */
export type AdminNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    permissions?: string[];
    /** Match the URL exactly rather than by prefix. */
    exact?: boolean;
    /**
     * Sub-destinations, rendered as a disclosure under the parent.
     *
     * A parent with children is a heading, not a link: clicking it opens the
     * group. This is what keeps a section like Settings — seven screens that
     * would otherwise outnumber every trading destination in the rail — to one
     * row until a staff member asks for it.
     */
    children?: AdminNavItem[];
};

export type AdminNavGroup = {
    label: string;
    items: AdminNavItem[];
};
