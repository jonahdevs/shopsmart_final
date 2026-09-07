<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Admin/StaffController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as inviteStaff,
    index as adminStaff,
} from '@/routes/admin/staff';

const { roleOptions } = defineProps<{
    roleOptions: App.Data.AdminRoleOptionData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Staff', href: adminStaff().url },
            { title: 'Invite', href: inviteStaff().url },
        ],
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Invite a colleague" />

        <!--
          The page header sits inside the form so the save button can read
          `processing` — the actions belong beside the title, not stranded at
          the bottom of a form somebody has already scrolled past.
        -->
        <Form
            v-bind="store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                eyebrow="System"
                title="Invite a colleague"
                description="They set their own password from the email we send. Nobody here ever types it."
            >
                <template #actions>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="adminStaff()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ processing ? 'Sending…' : 'Send invitation' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              A stack rather than a main column and an aside, to match Edit —
              which cannot split, because the invitation and revoke actions are
              their own POSTs and a form cannot nest inside this one.
            -->
            <AdminCard class="max-w-2xl">
                <AdminCardHeader title="Who are they?" />

                <div class="grid gap-4 px-5 py-5">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autocomplete="off"
                        />
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
                            The invitation goes here, and only whoever opens
                            this mailbox can set the password.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <AdminCard class="max-w-2xl">
                <AdminCardHeader title="What may they do?" />

                <div class="space-y-3 px-5 py-5">
                    <p class="text-muted-foreground text-sm">
                        Roles are what make somebody staff. Choose at least one
                        — an account with no role is a customer.
                    </p>

                    <label
                        v-for="role in roleOptions"
                        :key="role.id"
                        class="flex cursor-pointer items-start gap-3 text-sm"
                        :class="{
                            'cursor-not-allowed opacity-50': !role.assignable,
                        }"
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
                                    · carries permissions you do not hold, so
                                    you cannot grant it
                                </template>
                            </span>
                        </span>
                    </label>

                    <InputError :message="errors.roles" />
                </div>
            </AdminCard>
        </Form>
    </div>
</template>
