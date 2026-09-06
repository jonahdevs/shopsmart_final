<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, UserPlus } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatIsoDate } from '@/lib/utils';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Staff', href: '/admin/staff' },
        ],
    },
});

/**
 * The filter bar is local state synced to the URL, as on every other admin
 * table: a filtered list has to be a link somebody can send.
 */
const form = ref({
    search: filters.search ?? '',
    role: filters.role ?? '',
});

function activeQuery(overrides: Record<string, string | number> = {}) {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (filters.sort !== 'name' || filters.direction !== 'asc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    return { ...query, ...overrides };
}

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminStaff.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminStaff.url({ query: activeQuery({ page }) });
}

function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminStaff.url({ query: activeQuery({ sort: column, direction }) });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Staff" />

        <AdminPageHeader
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

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="staff-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="staff-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Name or email"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="staff-role">Role</Label>
                        <NativeSelect id="staff-role" v-model="form.role">
                            <option value="">All roles</option>
                            <option
                                v-for="role in roleOptions"
                                :key="role.id"
                                :value="role.name"
                            >
                                {{ role.name }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="staff.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    Nobody matches these filters.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :aria-sort="ariaSort('name')">
                                    <Link
                                        :href="sortHref('name')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Name
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('email')">
                                    <Link
                                        :href="sortHref('email')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Email
                                    </Link>
                                </TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead :aria-sort="ariaSort('created_at')">
                                    <Link
                                        :href="sortHref('created_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Added
                                    </Link>
                                </TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="member in staff" :key="member.id">
                                <TableCell class="font-medium">
                                    {{ member.name }}
                                    <Badge
                                        v-if="member.isSelf"
                                        variant="secondary"
                                        class="ml-2"
                                    >
                                        You
                                    </Badge>
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
                                        <Badge
                                            v-for="role in member.roles"
                                            :key="role"
                                            variant="outline"
                                        >
                                            {{ role }}
                                        </Badge>
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
                                      Your own account is managed in Settings,
                                      and an account whose roles you could not
                                      have granted is not yours to change — the
                                      server refuses both either way.
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
            </CardContent>
        </Card>

        <AdminPagination
            :pagination="pagination"
            :href-for-page="hrefForPage"
        />
    </div>
</template>
