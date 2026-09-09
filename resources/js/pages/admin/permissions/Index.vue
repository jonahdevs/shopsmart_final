<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { SelectItem } from '@/components/ui/select';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminPermissions } from '@/routes/admin/permissions';

type PermissionFilters = {
    search: string | null;
    group: string | null;
    sort: string;
    direction: string;
};

const { permissions, pagination, filters, groups } = defineProps<{
    permissions: App.Data.AdminPermissionRowData[];
    pagination: App.Data.PaginationData;
    filters: PermissionFilters;
    groups: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Permissions', href: adminPermissions().url },
        ],
    },
});

const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminPermissions.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'name', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
            group: filters.group ?? '',
        },
    });

/**
 * Three role badges, then a count.
 *
 * A permission every role holds would otherwise wrap the cell onto four lines
 * and push the table sideways, and the fourth name is not what the reader came
 * for — they came to know whether it is widely held.
 */
const SHOWN_ROLES = 3;
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Permissions" />

        <AdminPageHeader
            title="Permissions"
            :description="`${pagination.total} capabilit${pagination.total === 1 ? 'y' : 'ies'} defined in code and assigned to roles. Nothing here is editable — change what a role holds on the Roles screen.`"
        />

        <!--
          One card, three strips: filters, table, pagination. Not three cards —
          they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Permission name"
                search-label="Search permissions"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.group"
                    class="w-44"
                    label="Resource"
                    all-label="All resources"
                >
                    <SelectItem
                        v-for="group in groups"
                        :key="group.value"
                        :value="group.value"
                    >
                        {{ group.label }}
                    </SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="permissions.length === 0"
                :icon="KeyRound"
                :filtered="isFiltered"
                :title="
                    isFiltered
                        ? 'No matching permissions'
                        : 'No permissions yet'
                "
                :description="
                    isFiltered
                        ? 'No permissions match these filters.'
                        : 'Permissions are seeded from code. An empty table means the seeder has not run.'
                "
            />

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <AdminTable>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Permission"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <TableHead>Resource</TableHead>
                            <TableHead>Held by</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="permission in permissions"
                            :key="permission.id"
                        >
                            <TableCell class="font-medium">
                                {{ permission.label }}
                                <!--
                                  The name in the mono face because it is the
                                  string that appears in `can:` middleware and
                                  in the seeder, and somebody reading this table
                                  is usually about to go and find it there.
                                -->
                                <span
                                    class="text-muted-foreground block font-mono text-xs font-normal"
                                >
                                    {{ permission.name }}
                                </span>
                            </TableCell>

                            <TableCell>
                                <AdminStatusBadge
                                    tone="neutral"
                                    :label="permission.groupLabel"
                                />
                            </TableCell>

                            <TableCell>
                                <span
                                    v-if="permission.roleCount === 0"
                                    class="text-muted-foreground text-xs"
                                >
                                    No role
                                </span>
                                <span
                                    v-else
                                    class="flex flex-wrap items-center gap-1"
                                >
                                    <AdminStatusBadge
                                        v-for="role in permission.roles.slice(
                                            0,
                                            SHOWN_ROLES,
                                        )"
                                        :key="role"
                                        tone="neutral"
                                        :label="role"
                                    />
                                    <AdminStatusBadge
                                        v-if="
                                            permission.roleCount > SHOWN_ROLES
                                        "
                                        tone="neutral"
                                        :label="`+${permission.roleCount - SHOWN_ROLES}`"
                                    />
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </AdminTable>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
