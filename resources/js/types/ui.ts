export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/**
 * The shadcn-vue Badge variants. The server picks one — see the `badgeVariant()`
 * method on OrderStatus and PaymentStatus — but it crosses the wire as a plain
 * string, so `toBadgeVariant()` in lib/utils narrows it back before it reaches
 * the component's prop.
 */
export type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';
