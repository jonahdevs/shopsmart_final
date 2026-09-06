<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { store } from '@/actions/App/Http/Controllers/Admin/RoleController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as adminRoles } from '@/routes/admin/roles';

const { groups } = defineProps<{
    groups: App.Data.AdminPermissionGroupData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Roles', href: '/admin/roles' },
            { title: 'New role', href: '/admin/roles/create' },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="New role" />

        <AdminPageHeader
            title="New role"
            description="A role is a named set of permissions. Anyone holding it can reach exactly these screens and no others."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminRoles()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        All roles
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Form
            v-bind="store.form()"
            class="flex max-w-3xl flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Name</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-2">
                        <Label for="name" class="sr-only">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            placeholder="Warehouse"
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                    <CardDescription>
                        You can only grant a permission you hold yourself — the
                        rest are shown greyed out so the matrix stays honest
                        about what exists.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <fieldset v-for="group in groups" :key="group.resource">
                        <legend class="text-sm font-medium">
                            {{ group.label }}
                        </legend>
                        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                            <label
                                v-for="permission in group.permissions"
                                :key="permission.name"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                                :class="{
                                    'cursor-not-allowed opacity-50':
                                        !permission.holdable,
                                }"
                            >
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    :value="permission.name"
                                    :disabled="!permission.holdable"
                                    class="accent-primary size-4 rounded-sm"
                                />
                                {{ permission.label }}
                            </label>
                        </div>
                    </fieldset>

                    <InputError :message="errors.permissions" />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Creating…' : 'Create role' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminRoles()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
