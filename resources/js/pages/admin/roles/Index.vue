<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Plus, ShieldCheck } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/Admin/RoleController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create as createRole, edit as editRole } from '@/routes/admin/roles';

const { roles, permissionCount } = defineProps<{
    roles: App.Data.AdminRoleRowData[];
    permissionCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Roles', href: '/admin/roles' },
        ],
    },
});

/** Why a row's delete button is off, in the words the person needs. */
function blockedReason(role: App.Data.AdminRoleRowData): string {
    if (role.isProtected) {
        return 'Built in';
    }

    return `${role.memberCount} ${role.memberCount === 1 ? 'member' : 'members'}`;
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Roles" />

        <AdminPageHeader
            title="Roles and permissions"
            :description="`${roles.length} ${roles.length === 1 ? 'role' : 'roles'} across ${permissionCount} permissions. A role is the only thing that makes somebody staff.`"
        >
            <template #actions>
                <Button size="sm" as-child>
                    <Link :href="createRole()">
                        <Plus class="size-4" aria-hidden="true" />
                        New role
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Card>
            <CardContent class="pt-6">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead>Members</TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="role in roles" :key="role.id">
                                <TableCell class="font-medium">
                                    <span class="flex items-center gap-2">
                                        {{ role.name }}
                                        <ShieldCheck
                                            v-if="role.isProtected"
                                            class="text-muted-foreground size-4"
                                            aria-label="Built-in role"
                                        />
                                    </span>
                                    <span
                                        v-if="role.isProtected"
                                        class="text-muted-foreground block text-xs"
                                    >
                                        Built in — defined by the seeder, not
                                        editable here
                                    </span>
                                </TableCell>
                                <TableCell class="max-w-md">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="permission in role.permissions"
                                            :key="permission"
                                            variant="outline"
                                        >
                                            {{ permission }}
                                        </Badge>
                                        <span
                                            v-if="role.permissions.length === 0"
                                            class="text-muted-foreground text-sm"
                                        >
                                            No permissions yet
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="tabular-nums">
                                    {{ role.memberCount }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            v-if="role.editable"
                                            variant="ghost"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="editRole(role.id)">
                                                Edit
                                                <span class="sr-only">
                                                    {{ role.name }}
                                                </span>
                                            </Link>
                                        </Button>

                                        <Dialog v-if="role.deletable">
                                            <DialogTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive"
                                                >
                                                    Delete
                                                    <span class="sr-only">
                                                        {{ role.name }}
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
                                                            Delete the
                                                            {{ role.name }} role?
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            Nobody holds this role,
                                                            so nobody loses access.
                                                            It cannot be undone.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <InputError
                                                        :message="errors.name"
                                                    />

                                                    <DialogFooter class="gap-2">
                                                        <DialogClose as-child>
                                                            <Button
                                                                variant="secondary"
                                                            >
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            :disabled="processing"
                                                        >
                                                            {{
                                                                processing
                                                                    ? 'Deleting…'
                                                                    : 'Delete role'
                                                            }}
                                                        </Button>
                                                    </DialogFooter>
                                                </Form>
                                            </DialogContent>
                                        </Dialog>

                                        <span
                                            v-else
                                            class="text-muted-foreground px-2 text-xs"
                                        >
                                            {{ blockedReason(role) }}
                                        </span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
