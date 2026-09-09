<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Admin/RoleController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createRole,
    index as adminRoles,
} from '@/routes/admin/roles';

const { groups } = defineProps<{
    groups: App.Data.AdminPermissionGroupData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Roles', href: adminRoles().url },
            { title: 'New role', href: createRole().url },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="New role" />

        <!--
          The page header sits inside the form so Save is a real submit with
          `processing` in scope, rather than a button stranded at the bottom of
          a permission matrix somebody has already scrolled past.
        -->
        <Form
            v-bind="store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                title="New role"
                description="A role is a named set of permissions. Anyone holding it can reach exactly these screens and no others."
            >
                <template #actions>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="adminRoles()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ processing ? 'Creating…' : 'Create role' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              The name leads in the DOM so focus and a screen reader reach it
              before the matrix; on a wide screen `order` moves it into the
              aside, where the matrix keeps the two columns it needs to stay
              readable.
            -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:order-2">
                    <AdminCard>
                        <AdminCardHeader title="Name" />

                        <div class="grid gap-2 px-5 py-5">
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
                    </AdminCard>
                </div>

                <div class="space-y-6 lg:col-span-2">
                    <AdminCard>
                        <AdminCardHeader title="Permissions" />

                        <div class="space-y-6 px-5 py-5">
                            <p class="text-muted-foreground text-sm">
                                You can only grant a permission you hold
                                yourself — the rest are shown greyed out so the
                                matrix stays honest about what exists.
                            </p>

                            <fieldset
                                v-for="group in groups"
                                :key="group.resource"
                            >
                                <legend class="text-sm font-medium">
                                    {{ group.label }}
                                </legend>
                                <div
                                    class="mt-2 flex flex-wrap gap-x-6 gap-y-2"
                                >
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
                        </div>
                    </AdminCard>
                </div>
            </div>
        </Form>
    </div>
</template>
