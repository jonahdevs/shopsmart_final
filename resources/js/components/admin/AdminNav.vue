<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import type { AdminNavGroup, AdminNavItem } from '@/types';

const { groups } = defineProps<{
    groups: AdminNavGroup[];
}>();

const { canAny } = usePermissions();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

/**
 * Only the destinations this staff member may actually reach.
 *
 * An item with no `permissions` is open to anyone who got through the `staff`
 * middleware — the overview is the only such page. A group that empties out
 * disappears with its heading, so a Support role does not see a "System"
 * label with nothing under it.
 */
const visibleGroups = computed(() =>
    groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permissions?.length || canAny(...item.permissions),
            ),
        }))
        .filter((group) => group.items.length > 0),
);

/**
 * The overview owns `/admin`, which every other admin page sits under, so
 * matching it on prefix would light it up everywhere.
 */
function isActive(item: AdminNavItem): boolean {
    return item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <SidebarGroup
        v-for="group in visibleGroups"
        :key="group.label"
        class="px-2 py-0"
    >
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
