<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserPlus, Users } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as inviteStaff,
    edit as editStaff,
    index as adminStaff,
} from '@/routes/admin/staff';

type StaffFilters = {
    search: string | null;
    role: string | null;
    sort: string;
    direction: string;
};

const { staff, pagination, filters, roleOptions } = defineProps<{
    staff: App.Data.AdminStaffRowData[];
    pagination: App.Data.PaginationData;
    filters: StaffFilters;
    roleOptions: App.Data.AdminRoleOptionData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Staff', href: adminStaff().url },
        ],
    },
});

/**
 * The filter bar is local state synced to the URL, as on every other admin
 * table: a filtered list has to be a link somebody can send.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminStaff.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'name', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
            role: filters.role ?? '',
        },
    });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Staff" />

        <AdminPageHeader
            eyebrow="System"
            title="Staff"
            :description="`${pagination.total} ${pagination.total === 1 ? 'person has' : 'people have'} access to the admin panel.`"
        >
            <template #actions>
                <Button size="sm" as-child>
                    <Link :href="inviteStaff()">
                        <UserPlus class="size-4" aria-hidden="true" />
                        Invite colleague
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Name or email"
                search-label="Search staff"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <NativeSelect
                    v-model="form.role"
                    class="w-40"
                    aria-label="Role"
                >
                    <option value="">All roles</option>
                    <option
                        v-for="role in roleOptions"
                        :key="role.id"
                        :value="role.name"
                    >
                        {{ role.name }}
                    </option>
                </NativeSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="staff.length === 0"
                :icon="Users"
                :filtered="isFiltered"
                :title="isFiltered ? 'Nobody matches' : 'No staff yet'"
                :description="
                    isFiltered
                        ? 'Nobody matches these filters.'
                        : 'Invite a colleague and they will appear here.'
                "
            />

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Name"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <AdminSortableHead
                                label="Email"
                                :href="sortHref('email')"
                                :sort="ariaSort('email')"
                            />
                            <TableHead>Roles</TableHead>
                            <AdminSortableHead
                                label="Added"
                                :href="sortHref('created_at')"
                                :sort="ariaSort('created_at')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="member in staff" :key="member.id">
                            <TableCell class="font-medium">
                                <span class="flex items-center gap-2">
                                    {{ member.name }}
                                    <AdminStatusBadge
                                        v-if="member.isSelf"
                                        label="You"
                                        tone="brand"
                                    />
                                </span>
                            </TableCell>
                            <TableCell class="max-w-64">
                                <span class="block truncate">
                                    {{ member.email }}
                                </span>
                                <span
                                    v-if="member.invitationPending"
                                    class="text-muted-foreground block text-xs"
                                >
                                    Invitation not yet accepted
                                </span>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <AdminStatusBadge
                                        v-for="role in member.roles"
                                        :key="role"
                                        :label="role"
                                        tone="neutral"
                                    />
                                </div>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatIsoDate(member.createdAt) }}
                            </TableCell>
                            <TableCell>
                                <Button
                                    v-if="member.manageable"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="editStaff(member.id)">
                                        Edit
                                        <span class="sr-only">
                                            {{ member.name }}
                                        </span>
                                    </Link>
                                </Button>
                                <!--
                                  Your own account is managed in Settings, and
                                  an account whose roles you could not have
                                  granted is not yours to change — the server
                                  refuses both either way.
                                -->
                                <span
                                    v-else
                                    class="text-muted-foreground text-xs"
                                >
                                    {{
                                        member.isSelf
                                            ? 'Managed in Settings'
                                            : 'Above your access'
                                    }}
                                </span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
