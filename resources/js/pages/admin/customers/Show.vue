<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MapPin, Receipt, Star, Users, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCustomers } from '@/routes/admin/customers';
import { show as adminOrder } from '@/routes/admin/orders';
import { index as adminReviews } from '@/routes/admin/reviews';

const { detail } = defineProps<{
    detail: App.Data.AdminCustomerDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Customers', href: adminCustomers().url },
        ],
    },
});

/**
 * Hiding the edit form from a Support role is a courtesy, not the protection:
 * `can:customers.manage` on the route is what actually refuses the write.
 */
const { can } = usePermissions();
const canManage = computed(() => can('customers.manage'));
const canModerate = computed(() => can('reviews.manage'));
const canReadOrders = computed(() => can('orders.view'));

const customer = computed(() => detail.customer);

const stats = computed(() => [
    {
        label: 'Lifetime spend',
        value: customer.value.lifetimeSpentFormatted,
        note: `${detail.paidOrderCount} paid order${detail.paidOrderCount === 1 ? '' : 's'}`,
        icon: Wallet,
        tone: 'success' as const,
    },
    {
        label: 'Average order',
        value: detail.averageOrderValueFormatted,
        note: 'Paid orders only',
        icon: Receipt,
        tone: 'brand' as const,
    },
    {
        label: 'Orders placed',
        value: String(customer.value.orderCount),
        note: customer.value.lastOrderAt
            ? `Last ${formatIsoDate(customer.value.lastOrderAt)}`
            : 'None yet',
        icon: Users,
        tone: 'info' as const,
    },
    {
        label: 'Reviews written',
        value: String(detail.reviewCount),
        note: 'Across all products',
        icon: Star,
        tone: 'warning' as const,
    },
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="customer.name" />

        <AdminPageHeader
            :title="customer.name"
            :description="`Registered ${formatIsoDate(customer.registeredAt)}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCustomers()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back to customers
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="stat in stats"
                :key="stat.label"
                :label="stat.label"
                :value="stat.value"
                :hint="stat.note"
                :icon="stat.icon"
                :tone="stat.tone"
            />
        </div>

        <!--
          The record itself runs down the wide column; contact details are an
          aside because they are what you glance at, not what you work through.
        -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <AdminCard>
                    <AdminCardHeader title="Orders" :icon="Receipt" />

                    <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                        Every order placed on this account, newest first.
                    </p>

                    <AdminEmptyState
                        v-if="detail.orders.length === 0"
                        :icon="Receipt"
                        title="This customer has not ordered yet"
                    />

                    <div v-else class="overflow-x-auto">
                        <AdminTable>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Items</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Payment</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Placed</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="order in detail.orders"
                                    :key="order.id"
                                >
                                    <TableCell class="font-medium">
                                        <!--
                                          Reading customers and reading orders are
                                          separate permissions, so the link only
                                          appears for staff the server would admit.
                                        -->
                                        <Link
                                            v-if="canReadOrders"
                                            :href="
                                                adminOrder(order.orderNumber)
                                            "
                                            class="hover:text-primary transition-colors"
                                        >
                                            {{ order.orderNumber }}
                                        </Link>
                                        <span v-else>
                                            {{ order.orderNumber }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="tabular-nums">
                                        {{ order.itemCount }}
                                    </TableCell>
                                    <TableCell>
                                        <AdminStatusBadge
                                            :label="order.statusLabel"
                                            :variant="order.statusVariant"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <AdminStatusBadge
                                            :label="order.paymentStatusLabel"
                                            :variant="
                                                order.paymentStatusVariant
                                            "
                                        />
                                    </TableCell>
                                    <TableCell class="font-medium tabular-nums">
                                        {{ order.totalFormatted }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ formatIsoDate(order.placedAt) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </AdminTable>
                    </div>
                </AdminCard>

                <AdminCard>
                    <AdminCardHeader title="Reviews" :icon="Star" />

                    <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                        What this customer has written, whatever its moderation
                        state.
                    </p>

                    <AdminEmptyState
                        v-if="detail.reviews.length === 0"
                        :icon="Star"
                        title="No reviews written"
                    />

                    <ul v-else class="divide-y">
                        <li
                            v-for="review in detail.reviews"
                            :key="review.id"
                            class="px-5 py-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <AdminStatusBadge
                                    :label="review.statusLabel"
                                    :variant="review.statusVariant"
                                />
                                <span class="text-sm font-medium tabular-nums">
                                    {{ review.rating }}/5
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    on {{ review.productName }}
                                </span>
                                <span
                                    class="text-muted-foreground ml-auto text-xs"
                                >
                                    {{ formatIsoDate(review.submittedAt) }}
                                </span>
                            </div>
                            <p
                                v-if="review.title"
                                class="mt-2 text-sm font-medium"
                            >
                                {{ review.title }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ review.body }}
                            </p>
                        </li>
                    </ul>
                </AdminCard>
            </div>

            <div class="flex flex-col gap-6">
                <AdminCard>
                    <AdminCardHeader title="Contact" />

                    <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                        What the customer told us. Payment details are never
                        shown here.
                    </p>

                    <div class="space-y-4 px-5 py-4">
                        <div>
                            <p class="text-muted-foreground text-xs">Email</p>
                            <p class="text-sm break-all">
                                {{ customer.email }}
                            </p>
                            <AdminStatusBadge
                                :label="
                                    customer.emailVerifiedAt
                                        ? 'Verified'
                                        : 'Unverified'
                                "
                                :tone="
                                    customer.emailVerifiedAt
                                        ? 'success'
                                        : 'warning'
                                "
                                class="mt-1"
                            />
                        </div>

                        <Form
                            v-if="canManage"
                            v-bind="CustomerController.update.form(customer.id)"
                            :options="{ preserveScroll: true }"
                            class="space-y-2 border-t pt-4"
                            v-slot="{ errors, processing }"
                        >
                            <Label for="customer-name">Display name</Label>
                            <Input
                                id="customer-name"
                                name="name"
                                :default-value="customer.name"
                                required
                                maxlength="255"
                            />
                            <InputError :message="errors.name" />
                            <p class="text-muted-foreground text-xs">
                                The email address is changed by the customer
                                from their own settings, never from here.
                            </p>
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                            >
                                Save name
                            </Button>
                        </Form>
                    </div>
                </AdminCard>

                <AdminCard>
                    <AdminCardHeader title="Address book" :icon="MapPin" />

                    <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                        Where this customer asks deliveries to go.
                    </p>

                    <AdminEmptyState
                        v-if="detail.addresses.length === 0"
                        :icon="MapPin"
                        title="No saved addresses"
                    />

                    <ul v-else class="divide-y">
                        <li
                            v-for="address in detail.addresses"
                            :key="address.id ?? address.summary"
                            class="px-5 py-4 text-sm"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium">
                                    {{ address.fullName }}
                                </p>
                                <AdminStatusBadge
                                    v-if="address.isDefault"
                                    label="Default"
                                    tone="brand"
                                />
                            </div>
                            <p class="text-muted-foreground mt-1">
                                {{ address.summary }}
                            </p>
                            <p
                                v-if="address.phone"
                                class="text-muted-foreground mt-1"
                            >
                                {{ address.phone }}
                            </p>
                        </li>
                    </ul>
                </AdminCard>
            </div>
        </div>

        <p v-if="canModerate" class="text-muted-foreground text-sm">
            Moderating a review happens in the
            <Link :href="adminReviews()" class="underline">review queue</Link>.
        </p>
    </div>
</template>
