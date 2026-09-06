<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from '@lucide/vue';
import {
    destroy,
    invite,
    update,
} from '@/actions/App/Http/Controllers/Admin/StaffController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { index as adminStaff } from '@/routes/admin/staff';

const { member, roleOptions } = defineProps<{
    member: App.Data.AdminStaffRowData;
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
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="member.name" />

        <AdminPageHeader
            :title="member.name"
            description="Changing somebody's roles changes what they can reach the moment they save."
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

        <div v-if="member.invitationPending" class="flex flex-wrap items-center gap-3">
            <Badge variant="secondary">Invitation not yet accepted</Badge>
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
                    {{ processing ? 'Sending…' : 'Send the invitation again' }}
                </Button>
            </Form>
        </div>

        <Form
            v-bind="update.form(member.id)"
            class="flex max-w-2xl flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Roles</CardTitle>
                    <CardDescription>
                        At least one. To take away someone's access entirely, use
                        “Revoke staff access” below — it is a different decision
                        and it says what it does.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <label
                        v-for="role in roleOptions"
                        :key="role.id"
                        class="flex cursor-pointer items-start gap-3 text-sm"
                        :class="{ 'cursor-not-allowed opacity-50': !role.assignable }"
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
                    {{ processing ? 'Saving…' : 'Save changes' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminStaff()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <Card class="max-w-2xl border-destructive/30">
            <CardHeader>
                <CardTitle>Revoke staff access</CardTitle>
                <CardDescription>
                    This does not delete the account. Every role comes off, which
                    turns {{ member.name }} back into a customer — their orders,
                    addresses and reviews stay exactly as they are, and granting a
                    role again puts them straight back.
                </CardDescription>
            </CardHeader>
            <CardContent>
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
                                    {{ processing ? 'Revoking…' : 'Revoke access' }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    </div>
</template>
