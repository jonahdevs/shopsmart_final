import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { BadgeVariant } from '@/types/ui';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

/**
 * A date the page can print, read straight off an ISO-8601 string.
 *
 * The server sends its timestamps with the store's own offset attached, so the
 * calendar date is already the right one and the string is sliced rather than
 * parsed: `new Date()` would re-read it in whichever timezone the runtime
 * happens to be in, which is how an order placed at 1am in Nairobi ends up
 * dated the previous day, and how server-rendered markup ends up disagreeing
 * with the browser's rehydration of it.
 */
export function formatIsoDate(iso: string): string {
    const [year, month, day] = iso.slice(0, 10).split('-');

    return `${Number(day)} ${MONTHS[Number(month) - 1]} ${year}`;
}

/**
 * Narrow a server-sent badge variant back to the union Badge accepts.
 *
 * The enums in app/Enums decide which variant a status wears, but a PHP string
 * arrives here as `string`. Rather than casting at each call site, this checks
 * the value and falls back to the neutral variant — so a variant added to an
 * enum without a matching one here renders plainly instead of failing.
 */
export function toBadgeVariant(value: string): BadgeVariant {
    const variants: BadgeVariant[] = [
        'default',
        'outline',
        'destructive',
        'secondary',
    ];

    return variants.includes(value as BadgeVariant)
        ? (value as BadgeVariant)
        : 'outline';
}
