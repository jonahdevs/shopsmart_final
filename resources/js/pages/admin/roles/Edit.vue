<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { update } from '@/actions/App/Http/Controllers/Admin/RoleController';
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

const { role, groups } = defineProps<{
    role: App.Data.AdminRoleRowData;
    groups: App.Data.AdminPermissionGroupData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Roles', href: '/admin/roles' },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="`${role.name} role`" />

        <AdminPageHeader
            :title="role.name"
            :description="`${role.memberCount} ${role.memberCount === 1 ? 'person holds' : 'people hold'} this role. Saving changes what they can reach immediately.`"
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
            v-bind="update.form(role.id)"
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
                            :default-value="role.name"
                            required
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
                        Unticking one takes it away from everybody holding this
                        role, on their very next request.
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
                                    :default-checked="permission.granted"
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
                    {{ processing ? 'Saving…' : 'Save role' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminRoles()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
