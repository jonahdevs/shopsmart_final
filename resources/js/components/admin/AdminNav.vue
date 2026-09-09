<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import type { AdminNavGroup, AdminNavItem } from '@/types';

const { groups } = defineProps<{
    groups: AdminNavGroup[];
}>();

const { canAny } = usePermissions();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

/** Whether this staff member may reach a destination at all. */
function isPermitted(item: AdminNavItem): boolean {
    return !item.permissions?.length || canAny(...item.permissions);
}

/**
 * Only the destinations this staff member may actually reach.
 *
 * An item with no `permissions` is open to anyone who got through the `staff`
 * middleware — the overview is the only such page. Children are filtered on
 * their own permissions and a parent left with none disappears too, so a role
 * that may edit shipping but not branding opens Settings onto one screen rather
 * than onto six it will be refused. A group that empties out disappears with
 * its heading.
 */
const visibleGroups = computed(() =>
    groups
        .map((group) => ({
            ...group,
            items: group.items
                .filter(isPermitted)
                .map((item) => ({
                    ...item,
                    children: item.children?.filter(isPermitted),
                }))
                .filter((item) => !item.children || item.children.length > 0),
        }))
        .filter((group) => group.items.length > 0),
);

/**
 * The overview owns `/admin`, which every other admin page sits under, so
 * matching it on prefix would light it up everywhere.
 *
 * A row that declares `matches` stands for several screens rather than one, so
 * any of them lighting it up is the point.
 */
function isActive(item: AdminNavItem): boolean {
    if (item.matches) {
        return item.matches.some((href) => isCurrentOrParentUrl(href));
    }

    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

/** A parent is open when the page showing is one of its children. */
function isBranchOpen(item: AdminNavItem): boolean {
    return (item.children ?? []).some((child) => isActive(child));
}

/*
  The active row is a solid charcoal step out of the rail plus a 2px electric
  edge. The step alone is legible but ambiguous against hover; the edge is what
  says "you are here", and it is the same blue that does the pointing on the
  shop floor.
*/
const activeEdge =
    'relative data-[active=true]:before:absolute data-[active=true]:before:inset-y-1 data-[active=true]:before:left-0 data-[active=true]:before:w-0.5 data-[active=true]:before:rounded-full data-[active=true]:before:bg-sidebar-primary';
</script>

<template>
    <SidebarGroup
        v-for="group in visibleGroups"
        :key="group.label"
        class="px-2 py-0"
    >
        <SidebarGroupLabel
            class="text-sidebar-foreground/45 text-[0.625rem] font-bold tracking-[0.14em] uppercase"
        >
            {{ group.label }}
        </SidebarGroupLabel>

        <SidebarMenu>
            <template v-for="item in group.items" :key="item.title">
                <!--
                  A leaf: one row, one destination.
                -->
                <SidebarMenuItem v-if="!item.children">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                        :class="activeEdge"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!--
                  A branch. The parent is a disclosure rather than a link, so a
                  section whose screens have no index page — settings is the one
                  that matters — is still reachable from the rail. It opens by
                  default when the page showing is one of its children.
                -->
                <Collapsible
                    v-else
                    as-child
                    :default-open="isBranchOpen(item)"
                    class="group/branch"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="isBranchOpen(item)"
                                :class="activeEdge"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto size-3.5 transition-transform duration-200 group-data-[state=open]/branch:rotate-90"
                                    aria-hidden="true"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isActive(child)"
                                    >
                                        <Link :href="child.href">
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
