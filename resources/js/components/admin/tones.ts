/**
 * The semantic tints the back office is allowed to use.
 *
 * The shop floor runs on one blue on purpose — a shopper learns in one screen
 * that blue means "go here", and a second accent would cost them that. A back
 * office is the opposite problem: a staff member scanning forty rows needs to
 * tell paid from pending from refunded without reading, and four shades of one
 * hue cannot carry that.
 *
 * So this is the one place in the app where a colour vocabulary beyond the
 * brand palette lives, and it is closed: components pick a tone by name and
 * never write `bg-emerald-50` themselves. Adding a tone is a decision made
 * here, once, rather than in the thirty-fourth table that needed one.
 *
 * Every tone states its dark-mode pair. A tint defined only for light mode
 * turns into an unreadable slab the first time a staff member switches theme.
 */
export type AdminTone =
    | 'neutral'
    | 'brand'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'accent';

type ToneClasses = {
    /** A filled pill: status badges, counts, trend indicators. */
    pill: string;
    /** A tinted square behind an icon: stat tiles, activity rows. */
    chip: string;
    /** Foreground only, for a figure or an icon sitting on the card. */
    text: string;
};

export const adminTones: Record<AdminTone, ToneClasses> = {
    neutral: {
        pill: 'bg-muted text-muted-foreground',
        chip: 'bg-muted text-muted-foreground',
        text: 'text-muted-foreground',
    },
    brand: {
        pill: 'bg-accent text-accent-foreground',
        chip: 'bg-accent text-accent-foreground',
        text: 'text-primary',
    },
    success: {
        pill: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
        chip: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
        text: 'text-emerald-600 dark:text-emerald-400',
    },
    warning: {
        pill: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        chip: 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
        text: 'text-amber-600 dark:text-amber-400',
    },
    danger: {
        pill: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
        chip: 'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400',
        text: 'text-rose-600 dark:text-rose-400',
    },
    info: {
        pill: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
        chip: 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
        text: 'text-blue-600 dark:text-blue-400',
    },
    accent: {
        pill: 'bg-violet-100 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
        chip: 'bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400',
        text: 'text-violet-600 dark:text-violet-400',
    },
};

/**
 * Narrows the badge variant the server sends onto a tone.
 *
 * The enums in `app/Enums` describe a status with shadcn's four badge variants,
 * which is all the storefront needs. The back office wants the fuller
 * vocabulary above, so this is the seam: it is the only place that knows the
 * server's `default | secondary | destructive | outline` maps onto these tints,
 * and an enum that later grows a real tone name can be handled here without
 * touching a single page.
 */
export function toneForVariant(variant: string): AdminTone {
    const tones: Record<string, AdminTone> = {
        default: 'success',
        secondary: 'info',
        destructive: 'danger',
        outline: 'neutral',
        success: 'success',
        warning: 'warning',
        danger: 'danger',
        info: 'info',
        neutral: 'neutral',
        brand: 'brand',
        accent: 'accent',
    };

    return tones[variant] ?? 'neutral';
}
