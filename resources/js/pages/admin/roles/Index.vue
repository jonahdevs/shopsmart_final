<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    KeyRound,
    Lock,
    Plus,
    ShieldCheck,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/RoleController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { SelectItem } from '@/components/ui/select';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useIndexTable } from '@/composables/useIndexTable';
import { useInitials } from '@/composables/useInitials';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createRole,
    edit as editRole,
    index as adminRoles,
} from '@/routes/admin/roles';
import { create as inviteStaff, edit as editStaff } from '@/routes/admin/staff';

type StaffFilters = {
    search: string | null;
    role: string | null;
    sort: string;
    direction: string;
};

const { roles, permissionCount, canManageRoles, staff, pagination, filters } =
    defineProps<{
        roles: App.Data.AdminRoleRowData[];
        permissionCount: number;
        /** False for an Admin, who may hire but may not redefine a role. */
        canManageRoles: boolean;
        staff: App.Data.AdminStaffRowData[];
        pagination: App.Data.PaginationData;
        filters: StaffFilters;
        roleOptions: App.Data.AdminRoleOptionData[];
    }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Staff and roles', href: adminRoles().url },
        ],
    },
});

const { getInitials } = useInitials();

/**
 * The filter bar is local state synced to the URL, as on every other admin
 * table: a filtered list has to be a link somebody can send. The role cards
 * above are unfiltered — there are never enough of them to need it.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminRoles.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'name', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
            role: filters.role ?? '',
        },
    });

/**
 * A card each, not a row each.
 *
 * A role is four facts — what it is called, how much it can do, how many people
 * hold it, and whether it may be changed — and a table spends its width on the
 * one of those that does not fit: the permission list. The count and an Edit
 * link answer the question the list was standing in for, and the matrix behind
 * Edit is where a permission is actually read.
 */
function memberLabel(role: App.Data.AdminRoleRowData): string {
    return `${role.memberCount} ${role.memberCount === 1 ? 'member' : 'members'}`;
}

function permissionLabel(role: App.Data.AdminRoleRowData): string {
    return `${role.permissionCount} of ${permissionCount} permissions`;
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Staff and roles" />

        <AdminPageHeader
            title="Staff and roles"
            :description="
                canManageRoles
                    ? `${roles.length} ${roles.length === 1 ? 'role' : 'roles'} across ${permissionCount} permissions, held by ${pagination.total} ${pagination.total === 1 ? 'person' : 'people'}. A role is the only thing that makes somebody staff.`
                    : `${pagination.total} ${pagination.total === 1 ? 'person' : 'people'} with access to this panel. A role is the only thing that makes somebody staff.`
            "
        >
            <template #actions>
                <Button v-if="canManageRoles" variant="outline" as-child>
                    <Link :href="createRole()">
                        <Plus class="size-4" aria-hidden="true" />
                        New role
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="inviteStaff()">
                        <UserPlus class="size-4" aria-hidden="true" />
                        Invite staff
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!--
          The cards are hidden rather than emptied for an Admin: they may hire
          and demote but may not redefine what a role means, and a grid of cards
          whose only action is refused would be a worse answer than no grid.
        -->
        <template v-if="canManageRoles">
            <!--
              Roles are never filtered, so an empty grid can only mean the
              seeder has not run — the invitation to create one is always the
              right offer.
            -->
            <AdminCard v-if="roles.length === 0">
                <AdminEmptyState
                    :icon="ShieldCheck"
                    title="No roles yet"
                    description="Until a role exists, nobody can be made staff."
                >
                    <template #action>
                        <Button as-child>
                            <Link :href="createRole()">
                                <Plus class="size-4" aria-hidden="true" />
                                New role
                            </Link>
                        </Button>
                    </template>
                </AdminEmptyState>
            </AdminCard>

            <ul
                v-else
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <li v-for="role in roles" :key="role.id">
                    <AdminCard class="flex h-full flex-col justify-between">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="text-base font-semibold">
                                    {{ role.name }}
                                </h2>

                                <!--
                              One face, as a hint that this role is somebody's
                              access rather than an empty definition. Who they
                              all are is the Staff screen's job.
                            -->
                                <Avatar
                                    v-if="role.firstMemberName"
                                    class="size-7 shrink-0"
                                    :title="`${role.firstMemberName} and ${role.memberCount - 1} other${role.memberCount === 2 ? '' : 's'}`"
                                >
                                    <AvatarFallback class="text-[0.625rem]">
                                        {{ getInitials(role.firstMemberName) }}
                                    </AvatarFallback>
                                </Avatar>
                            </div>

                            <dl
                                class="text-muted-foreground mt-4 space-y-1.5 text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <KeyRound
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <dt class="sr-only">Permissions</dt>
                                    <dd class="tabular-nums">
                                        {{ permissionLabel(role) }}
                                    </dd>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Users class="size-4" aria-hidden="true" />
                                    <dt class="sr-only">Members</dt>
                                    <dd class="tabular-nums">
                                        {{ memberLabel(role) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-t px-5 py-3"
                        >
                            <Button
                                v-if="role.editable"
                                variant="link"
                                size="sm"
                                class="h-auto p-0"
                                as-child
                            >
                                <Link :href="editRole(role.id)">
                                    Edit role
                                    <span class="sr-only">{{ role.name }}</span>
                                </Link>
                            </Button>
                            <span v-else class="text-muted-foreground text-sm">
                                Built in
                            </span>

                            <Lock
                                v-if="role.isProtected"
                                class="text-muted-foreground size-4 shrink-0"
                                aria-label="Built in — defined by the seeder, not editable here"
                            />

                            <!--
                          Absent rather than disabled when the role has members:
                          a greyed button invites a click that would be refused,
                          and the honest next step is to move them first.
                        -->
                            <Dialog v-else-if="role.deletable">
                                <DialogTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span class="sr-only">
                                            Delete {{ role.name }}
                                        </span>
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        v-bind="destroy.form(role.id)"
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ errors, processing }"
                                        class="space-y-6"
                                    >
                                        <DialogHeader class="space-y-3">
                                            <DialogTitle>
                                                Delete the {{ role.name }} role?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Nobody holds this role, so
                                                nobody loses access. It cannot
                                                be undone.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <InputError :message="errors.name" />

                                        <DialogFooter class="gap-2">
                                            <DialogClose as-child>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                :disabled="processing"
                                            >
                                                Delete role
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <!--
                          A role with members cannot be deleted — that would
                          silently turn each of them back into a customer — so
                          the slot says why instead of offering a refused
                          button. The people in question are in the table below.
                        -->
                            <span v-else class="text-muted-foreground text-xs">
                                {{ memberLabel(role) }}
                            </span>
                        </div>
                    </AdminCard>
                </li>
            </ul>
        </template>

        <!--
          One card, four strips: heading, filters, table, pagination. The
          heading is here rather than in the page header because the page now
          holds two lists and the reader needs to know which one this is.
        -->
        <AdminCard>
            <AdminCardHeader title="Staff" />

            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Name or email"
                search-label="Search staff"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.role"
                    class="w-44"
                    label="Role"
                    all-label="All roles"
                >
                    <SelectItem
                        v-for="option in roleOptions"
                        :key="option.name"
                        :value="option.name"
                    >
                        {{ option.name }}
                    </SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="staff.length === 0"
                :icon="Users"
                :filtered="isFiltered"
                :title="isFiltered ? 'Nobody matches' : 'No staff yet'"
                :description="
                    isFiltered
                        ? 'No staff member matches these filters.'
                        : 'Inviting a colleague gives them a role, and a role is what makes them staff.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="inviteStaff()">
                            <UserPlus class="size-4" aria-hidden="true" />
                            Invite staff
                        </Link>
                    </Button>
                </template>
            </AdminEmptyState>

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <AdminTable>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Member"
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
                                <span class="flex items-center gap-3">
                                    <Avatar class="size-8 shrink-0">
                                        <AvatarFallback class="text-[0.625rem]">
                                            {{ getInitials(member.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        class="flex items-center gap-2 truncate"
                                    >
                                        {{ member.name }}
                                        <AdminStatusBadge
                                            v-if="member.isSelf"
                                            label="You"
                                            tone="brand"
                                        />
                                    </span>
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
                </AdminTable>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
