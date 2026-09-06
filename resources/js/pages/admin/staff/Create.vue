<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { store } from '@/actions/App/Http/Controllers/Admin/StaffController';
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
import { index as adminStaff } from '@/routes/admin/staff';

const { roleOptions } = defineProps<{
    roleOptions: App.Data.AdminRoleOptionData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Staff', href: '/admin/staff' },
            { title: 'Invite', href: '/admin/staff/create' },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Invite a colleague" />

        <AdminPageHeader
            title="Invite a colleague"
            description="They set their own password from the email we send. Nobody here ever types it."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminStaff()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        All staff
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Form
            v-bind="store.form()"
            class="flex max-w-2xl flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Who are they?</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" required autocomplete="off" />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.email" />
                        <p class="text-muted-foreground text-sm">
                            The invitation goes here, and only whoever opens this
                            mailbox can set the password.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>What may they do?</CardTitle>
                    <CardDescription>
                        Roles are what make somebody staff. Choose at least one —
                        an account with no role is a customer.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label
                        v-for="role in roleOptions"
                        :key="role.id"
                        class="flex cursor-pointer items-start gap-3 text-sm"
                        :class="{ 'cursor-not-allowed opacity-50': !role.assignable }"
                    >
                        <!--
                          A plain checkbox: `roles[]` is what Inertia turns into
                          an array, and the value is the role name the server
                          validates against the list it is willing to hand out.
                        -->
                        <input
                            type="checkbox"
                            name="roles[]"
                            :value="role.name"
                            :disabled="!role.assignable"
                            class="accent-primary mt-0.5 size-4 rounded-sm"
                        />
                        <span>
                            <span class="font-medium">{{ role.name }}</span>
                            <span class="text-muted-foreground block text-xs">
                                {{ role.permissionCount }}
                                {{
                                    role.permissionCount === 1
                                        ? 'permission'
                                        : 'permissions'
                                }}
                                <template v-if="!role.assignable">
                                    · carries permissions you do not hold, so you
                                    cannot grant it
                                </template>
                            </span>
                        </span>
                    </label>

                    <InputError :message="errors.roles" />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ processing ? 'Sending…' : 'Send invitation' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminStaff()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
