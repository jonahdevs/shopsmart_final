<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
import { index as adminCustomers } from '@/routes/admin/customers';
import { show as adminOrder } from '@/routes/admin/orders';
import { index as adminReviews } from '@/routes/admin/reviews';

const { detail } = defineProps<{
    detail: App.Data.AdminCustomerDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Customers', href: '/admin/customers' },
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
    },
    {
        label: 'Average order',
        value: detail.averageOrderValueFormatted,
        note: 'Paid orders only',
    },
    {
        label: 'Orders placed',
        value: String(customer.value.orderCount),
        note: customer.value.lastOrderAt
            ? `Last ${formatIsoDate(customer.value.lastOrderAt)}`
            : 'None yet',
    },
    {
        label: 'Reviews written',
        value: String(detail.reviewCount),
        note: 'Across all products',
    },
]);
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="customer.name" />

        <AdminPageHeader
            :title="customer.name"
            :description="`Registered ${formatIsoDate(customer.registeredAt)}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCustomers()">Back to customers</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="stat in stats" :key="stat.label">
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">
                        {{ stat.label }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ stat.value }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ stat.note }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-1">
                <CardHeader>
                    <CardTitle>Contact</CardTitle>
                    <CardDescription>
                        What the customer told us. Payment details are never
                        shown here.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="text-muted-foreground text-xs">Email</p>
                        <p class="text-sm break-all">{{ customer.email }}</p>
                        <Badge
                            :variant="
                                customer.emailVerifiedAt ? 'default' : 'outline'
                            "
                            class="mt-1"
                        >
                            {{
                                customer.emailVerifiedAt
                                    ? 'Verified'
                                    : 'Unverified'
                            }}
                        </Badge>
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
                            The email address is changed by the customer from
                            their own settings, never from here.
                        </p>
                        <Button type="submit" size="sm" :disabled="processing">
                            Save name
                        </Button>
                    </Form>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Address book</CardTitle>
                    <CardDescription>
                        Where this customer asks deliveries to go.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="detail.addresses.length === 0"
                        class="text-muted-foreground py-6 text-center text-sm"
                    >
                        No saved addresses.
                    </p>

                    <ul v-else class="grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="address in detail.addresses"
                            :key="address.id ?? address.summary"
                            class="rounded-lg border p-3 text-sm"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium">
                                    {{ address.fullName }}
                                </p>
                                <Badge v-if="address.isDefault">Default</Badge>
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
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Orders</CardTitle>
                <CardDescription>
                    Every order placed on this account, newest first.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="detail.orders.length === 0"
                    class="text-muted-foreground py-6 text-center text-sm"
                >
                    This customer has not ordered yet.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead class="text-right">Total</TableHead>
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
                                        :href="adminOrder(order.orderNumber)"
                                        class="hover:underline"
                                    >
                                        {{ order.orderNumber }}
                                    </Link>
                                    <span v-else>{{ order.orderNumber }}</span>
                                </TableCell>
                                <TableCell class="tabular-nums">
                                    {{ order.itemCount }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            toBadgeVariant(order.statusVariant)
                                        "
                                    >
                                        {{ order.statusLabel }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            toBadgeVariant(
                                                order.paymentStatusVariant,
                                            )
                                        "
                                    >
                                        {{ order.paymentStatusLabel }}
                                    </Badge>
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    {{ order.totalFormatted }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatIsoDate(order.placedAt) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Reviews</CardTitle>
                <CardDescription>
                    What this customer has written, whatever its moderation
                    state.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="detail.reviews.length === 0"
                    class="text-muted-foreground py-6 text-center text-sm"
                >
                    No reviews written.
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="review in detail.reviews"
                        :key="review.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                :variant="toBadgeVariant(review.statusVariant)"
                            >
                                {{ review.statusLabel }}
                            </Badge>
                            <span class="text-sm font-medium tabular-nums">
                                {{ review.rating }}/5
                            </span>
                            <span class="text-muted-foreground text-sm">
                                on {{ review.productName }}
                            </span>
                            <span class="text-muted-foreground ml-auto text-xs">
                                {{ formatIsoDate(review.submittedAt) }}
                            </span>
                        </div>
                        <p v-if="review.title" class="mt-2 text-sm font-medium">
                            {{ review.title }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ review.body }}
                        </p>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <p v-if="canModerate" class="text-muted-foreground text-sm">
            Moderating a review happens in the
            <Link :href="adminReviews()" class="underline">review queue</Link>.
        </p>
    </div>
</template>
