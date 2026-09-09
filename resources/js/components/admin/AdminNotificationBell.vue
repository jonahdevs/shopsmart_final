<script setup lang="ts">
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { Bell, CreditCard, Package } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { adminTones, type AdminTone } from '@/components/admin/tones';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    markAllRead,
    show,
} from '@/actions/App/Http/Controllers/Admin/NotificationController';

/**
 * The staff notification bell.
 *
 * Two props feed it and they are fetched very differently, because they cost
 * very differently. `unreadCount` rides every admin response so the badge is
 * right the moment a page paints. `items` is an optional prop — the server
 * resolves it only when it is asked for by name — so the fifteen rows are
 * queried when the panel opens and never on an ordinary page load.
 *
 * Refreshed by polling rather than by a websocket. Broadcasting would mean Echo
 * plus a websocket server, which is a dependency and a piece of infrastructure
 * this project does not have, and a bell is the one feature that does not need
 * either: nobody is worse off learning about an order fifty seconds late.
 * Inertia throttles the poll to a tenth while the tab is in the background, so
 * a forgotten admin tab costs almost nothing.
 */

/** A minute. See the poll's comment below for why this number. */
const POLL_INTERVAL_MS = 60_000;

const page = usePage();

const unreadCount = computed(() => page.props.notifications.unreadCount);
const items = computed(() => page.props.notifications.items);

/*
  Only the badge. A partial reload still runs the page's controller, so the
  narrower `only` does not save the server the page — it saves it serialising
  one, and it saves the bell's fifteen-row query, which stays optional and
  unasked-for. A minute is the interval because an order is not an incident:
  the shop acts on it in minutes, and a shorter tick would multiply the page's
  own cost for a badge nobody is watching change.
*/
usePoll(POLL_INTERVAL_MS, { only: ['notifications.unreadCount'] });

const open = ref(false);

/*
  The list is asked for on open, every time, rather than once and cached. The
  panel's whole job is to say what has happened since the page was loaded, and
  a staff member leaves an admin screen open for hours.
*/
watch(open, (isOpen) => {
    if (isOpen) {
        router.reload({ only: ['notifications.items'] });
    }
});

const ICONS = {
    order: Package,
    payment: CreditCard,
} as const;

/**
 * The same glyphs the sidebar uses for Orders and Payments, so a notification
 * looks like the place it is about to take you.
 */
const iconFor = (icon: string) =>
    ICONS[icon as keyof typeof ICONS] ?? ICONS.order;

const chipFor = (tone: string) =>
    adminTones[tone as AdminTone]?.chip ?? adminTones.neutral.chip;

/** Nine and a bit — past that the number stops being read and starts being a shape. */
const badgeLabel = computed(() =>
    unreadCount.value > 9 ? '9+' : String(unreadCount.value),
);

const clearAll = () => {
    router.post(
        markAllRead.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            /*
              Both keys named in full. `items` is optional, so it is sent only
              when it is asked for by its own path — naming the parent alone
              would clear the badge and leave the open panel holding rows that
              still look unread.
            */
            only: ['notifications.unreadCount', 'notifications.items'],
        },
    );
};
</script>

<template>
    <Popover v-model:open="open">
        <Tooltip>
            <TooltipTrigger as-child>
                <PopoverTrigger as-child>
                    <Button variant="ghost" size="icon">
                        <!--
                          The count is pinned to the glyph, not to the button,
                          so it sits on the bell's shoulder rather than in the
                          corner of the tap target — the same treatment the
                          storefront header gives its cart count.
                        -->
                        <span class="relative">
                            <Bell class="size-4" aria-hidden="true" />
                            <span
                                v-if="unreadCount > 0"
                                class="bg-primary text-primary-foreground ring-card absolute -top-1.5 -right-2 min-w-[1rem] rounded-full px-1 text-center text-[10px] leading-4 font-bold tabular-nums ring-2"
                                aria-hidden="true"
                            >
                                {{ badgeLabel }}
                            </span>
                        </span>
                        <span class="sr-only">
                            Notifications{{
                                unreadCount > 0 ? `, ${unreadCount} unread` : ''
                            }}
                        </span>
                    </Button>
                </PopoverTrigger>
            </TooltipTrigger>
            <TooltipContent>Notifications</TooltipContent>
        </Tooltip>

        <PopoverContent
            align="end"
            class="w-80 max-w-[calc(100vw-1rem)] overflow-hidden p-0"
            data-test="notification-panel"
        >
            <div class="flex items-center justify-between border-b px-4 py-3">
                <span
                    class="text-xs font-semibold tracking-wide uppercase"
                    id="notification-panel-title"
                >
                    Notifications
                </span>
                <Button
                    v-if="unreadCount > 0"
                    variant="ghost"
                    size="sm"
                    class="-mr-2 h-auto px-2 py-1 text-xs"
                    data-test="mark-all-read"
                    @click="clearAll"
                >
                    Mark all read
                </Button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <!--
                  `items` is undefined until the optional prop lands, which is
                  a different state from "landed and empty" — the skeleton says
                  so rather than flashing the empty state on every open.
                -->
                <div v-if="items === undefined" class="space-y-3 p-4">
                    <div v-for="row in 3" :key="row" class="flex gap-3">
                        <Skeleton class="size-8 shrink-0 rounded-full" />
                        <div class="min-w-0 flex-1 space-y-2">
                            <Skeleton class="h-3 w-3/5" />
                            <Skeleton class="h-3 w-4/5" />
                        </div>
                    </div>
                </div>

                <p
                    v-else-if="items.length === 0"
                    class="text-muted-foreground px-4 py-10 text-center text-sm"
                >
                    Nothing to catch up on.
                </p>

                <template v-else>
                    <Link
                        v-for="notification in items"
                        :key="notification.id"
                        :href="show(notification.id)"
                        class="hover:bg-muted/60 focus-visible:outline-ring flex items-start gap-3 border-b px-4 py-3 last:border-0 focus-visible:-outline-offset-2 focus-visible:outline-2"
                        @click="open = false"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                            :class="chipFor(notification.tone)"
                        >
                            <component
                                :is="iconFor(notification.icon)"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-sm leading-snug"
                                :class="
                                    notification.isUnread
                                        ? 'font-semibold'
                                        : 'font-medium'
                                "
                            >
                                {{ notification.title }}
                            </span>
                            <span
                                class="text-muted-foreground mt-0.5 block truncate text-xs"
                            >
                                {{ notification.body }}
                            </span>
                            <time
                                class="text-muted-foreground mt-0.5 block text-xs"
                                :datetime="notification.createdAt"
                            >
                                {{ notification.createdAtForHumans }}
                            </time>
                        </span>

                        <span
                            v-if="notification.isUnread"
                            class="bg-primary mt-2 size-1.5 shrink-0 rounded-full"
                        >
                            <span class="sr-only">Unread</span>
                        </span>
                    </Link>
                </template>
            </div>
        </PopoverContent>
    </Popover>
</template>
