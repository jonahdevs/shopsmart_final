<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import {
    destroy,
    invite,
    update,
} from '@/actions/App/Http/Controllers/Admin/StaffController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminStaff } from '@/routes/admin/staff';

const { member, roleOptions } = defineProps<{
    member: App.Data.AdminStaffRowData;
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
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="member.name" />

        <!--
          The page header sits inside the form so the save button can read
          `processing` — the actions belong beside the title, not stranded at
          the bottom of a form somebody has already scrolled past.
        -->
        <Form
            v-bind="update.form(member.id)"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                eyebrow="System"
                :title="member.name"
                description="Changing somebody's roles changes what they can reach the moment they save."
            >
                <template #actions>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="adminStaff()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              A stack rather than a main column and an aside: the two cards
              that would fill an aside — the invitation and the revoke — are
              each their own POST, and a form cannot nest inside this one.
            -->
            <AdminCard class="max-w-2xl">
                <AdminCardHeader title="Account">
                    <template #actions>
                        <AdminStatusBadge
                            v-if="member.invitationPending"
                            label="Invitation not yet accepted"
                            variant="secondary"
                        />
                    </template>
                </AdminCardHeader>

                <div class="grid gap-4 px-5 py-5">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="member.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            :default-value="member.email"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>
                </div>
            </AdminCard>

            <AdminCard class="max-w-2xl">
                <AdminCardHeader title="Roles" />

                <div class="space-y-3 px-5 py-5">
                    <p class="text-muted-foreground text-sm">
                        At least one. To take away someone's access entirely,
                        use “Revoke staff access” below — it is a different
                        decision and it says what it does.
                    </p>

                    <label
                        v-for="role in roleOptions"
                        :key="role.id"
                        class="flex cursor-pointer items-start gap-3 text-sm"
                        :class="{
                            'cursor-not-allowed opacity-50': !role.assignable,
                        }"
                    >
                        <input
                            type="checkbox"
                            name="roles[]"
                            :value="role.name"
                            :disabled="!role.assignable"
                            :default-checked="member.roles.includes(role.name)"
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

        <!--
          Resending is its own POST, and a form cannot be nested inside the one
          above — so it lives down here with the other account-level action
          rather than beside the badge that announces it.
        -->
        <AdminCard v-if="member.invitationPending" class="max-w-2xl">
            <AdminCardHeader title="Invitation" />

            <div class="space-y-3 px-5 py-5">
                <p class="text-muted-foreground text-sm">
                    Nobody has accepted this invitation yet. Sending it again
                    emails {{ member.name }} a fresh link to set a password.
                </p>

                <Form
                    v-bind="invite.form(member.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                    >
                        <Mail class="size-4" aria-hidden="true" />
                        {{
                            processing
                                ? 'Sending…'
                                : 'Send the invitation again'
                        }}
                    </Button>
                </Form>
            </div>
        </AdminCard>

        <AdminCard class="border-destructive/30 max-w-2xl">
            <AdminCardHeader title="Revoke staff access" />

            <div class="space-y-3 px-5 py-5">
                <p class="text-muted-foreground text-sm">
                    This does not delete the account. Every role comes off,
                    which turns {{ member.name }} back into a customer — their
                    orders, addresses and reviews stay exactly as they are, and
                    granting a role again puts them straight back.
                </p>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="destructive" size="sm">
                            Revoke staff access
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="destroy.form(member.id)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                            class="space-y-6"
                        >
                            <DialogHeader class="space-y-3">
                                <DialogTitle>
                                    Revoke {{ member.name }}'s access?
                                </DialogTitle>
                                <DialogDescription>
                                    They will keep their account and their order
                                    history, but they will no longer be able to
                                    open the admin panel.
                                </DialogDescription>
                            </DialogHeader>

                            <InputError :message="errors.roles" />

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                >
                                    {{
                                        processing
                                            ? 'Revoking…'
                                            : 'Revoke access'
                                    }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminCard>
    </div>
</template>
