<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
import { note, status } from '@/actions/App/Http/Controllers/Admin/OrderController';
import { index as adminOrders } from '@/routes/admin/orders';

const { detail } = defineProps<{
    detail: App.Data.AdminOrderDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Orders', href: '/admin/orders' },
        ],
    },
});

const { can } = usePermissions();

const order = detail.order;
const totals = order.totals;
const address = order.shippingAddress;
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="`Order ${order.orderNumber}`" />

        <AdminPageHeader
            :title="order.orderNumber"
            :description="`Placed ${formatIsoDate(order.placedAt)} by ${order.customerName}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminOrders()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        All orders
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="toBadgeVariant(order.statusVariant)">
                {{ order.statusLabel }}
            </Badge>
            <Badge :variant="toBadgeVariant(order.paymentStatusVariant)">
                {{ order.paymentStatusLabel }}
            </Badge>
            <span v-if="order.paymentMethod" class="text-muted-foreground text-sm">
                via {{ order.paymentMethod }}
            </span>
            <span
                v-if="detail.stockDeducted"
                class="text-muted-foreground text-sm"
            >
                · stock taken
            </span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead class="text-right">
                                            Unit
                                        </TableHead>
                                        <TableHead class="text-right">
                                            Qty
                                        </TableHead>
                                        <TableHead class="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="line in order.lines"
                                        :key="`${line.productId}-${line.variantId}`"
                                    >
                                        <TableCell>
                                            <span class="font-medium">
                                                {{ line.name }}
                                            </span>
                                            <span
                                                v-if="line.optionLabel"
                                                class="text-muted-foreground block text-xs"
                                            >
                                                {{ line.optionLabel }}
                                            </span>
                                            <span
                                                v-if="line.sku"
                                                class="text-muted-foreground block text-xs"
                                            >
                                                {{ line.sku }}
                                            </span>
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{ line.unitPriceFormatted }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{ line.quantity }}
                                        </TableCell>
                                        <TableCell
                                            class="text-right font-medium tabular-nums"
                                        >
                                            {{ line.totalFormatted }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <Separator class="my-4" />

                        <dl class="ml-auto max-w-xs space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd class="tabular-nums">
                                    {{ totals.subtotalFormatted }}
                                </dd>
                            </div>
                            <div
                                v-if="totals.discountCents > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-muted-foreground">
                                    Discount
                                    <span v-if="totals.couponCode">
                                        ({{ totals.couponCode }})
                                    </span>
                                </dt>
                                <dd class="tabular-nums">
                                    −{{ totals.discountFormatted }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Delivery</dt>
                                <dd class="tabular-nums">
                                    {{ totals.shippingFormatted }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    {{ totals.taxLabel }}
                                </dt>
                                <dd class="tabular-nums">
                                    {{ totals.taxFormatted }}
                                </dd>
                            </div>
                            <Separator class="my-2" />
                            <div class="flex justify-between font-semibold">
                                <dt>Total</dt>
                                <dd class="tabular-nums">
                                    {{ totals.totalFormatted }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="detail.payments.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            No collection has been attempted for this order.
                        </p>

                        <div v-else class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reference</TableHead>
                                        <TableHead>Gateway</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead>Attempted</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="payment in detail.payments"
                                        :key="payment.id"
                                    >
                                        <TableCell
                                            class="font-mono text-xs break-all"
                                        >
                                            {{ payment.reference }}
                                        </TableCell>
                                        <TableCell>
                                            {{ payment.gateway }}
                                            <span
                                                v-if="payment.channel"
                                                class="text-muted-foreground block text-xs"
                                            >
                                                {{ payment.channel }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    toBadgeVariant(
                                                        payment.statusVariant,
                                                    )
                                                "
                                            >
                                                {{ payment.statusLabel }}
                                            </Badge>
                                            <span
                                                v-if="payment.failureReason"
                                                class="text-muted-foreground block text-xs"
                                            >
                                                {{ payment.failureReason }}
                                            </span>
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{ payment.amountFormatted }}
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">
                                            {{ formatIsoDate(payment.createdAt) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Customer</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p class="font-medium">{{ order.customerName }}</p>
                        <p class="text-muted-foreground break-all">
                            {{ order.customerEmail }}
                        </p>
                        <p v-if="order.customerPhone" class="text-muted-foreground">
                            {{ order.customerPhone }}
                        </p>
                        <p
                            v-if="detail.customerId === null"
                            class="text-muted-foreground pt-2 text-xs"
                        >
                            This account has since been deleted. The order keeps
                            its own record of who placed it.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="address">
                    <CardHeader>
                        <CardTitle>Delivery</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-0.5 text-sm">
                        <p>{{ address.firstName }} {{ address.lastName }}</p>
                        <p class="text-muted-foreground">{{ address.line1 }}</p>
                        <p v-if="address.line2" class="text-muted-foreground">
                            {{ address.line2 }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ address.city }}
                            <span v-if="address.county">
                                , {{ address.county }}
                            </span>
                        </p>
                        <p v-if="address.phone" class="text-muted-foreground">
                            {{ address.phone }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="order.customerNote">
                    <CardHeader>
                        <CardTitle>Customer note</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-line">
                            {{ order.customerNote }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="can('orders.manage')">
                    <CardHeader>
                        <CardTitle>Move status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="detail.availableStatuses.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            This order has reached a final status and cannot be
                            moved again.
                        </p>

                        <Form
                            v-else
                            v-bind="status.form(order.orderNumber)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                            class="space-y-3"
                        >
                            <div class="space-y-1.5">
                                <Label for="order-status">New status</Label>
                                <NativeSelect id="order-status" name="status">
                                    <option
                                        v-for="option in detail.availableStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </NativeSelect>
                                <p
                                    v-if="errors.status"
                                    class="text-destructive text-sm"
                                >
                                    {{ errors.status }}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                size="sm"
                                :disabled="processing"
                            >
                                {{ processing ? 'Saving…' : 'Update status' }}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>

                <Card v-if="can('orders.manage')">
                    <CardHeader>
                        <CardTitle>Internal note</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="note.form(order.orderNumber)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                            class="space-y-3"
                        >
                            <div class="space-y-1.5">
                                <Label for="staff-note" class="sr-only">
                                    Internal note
                                </Label>
                                <Textarea
                                    id="staff-note"
                                    name="staff_note"
                                    rows="4"
                                    :default-value="detail.staffNote ?? ''"
                                    placeholder="Only staff can see this."
                                />
                                <p
                                    v-if="errors.staff_note"
                                    class="text-destructive text-sm"
                                >
                                    {{ errors.staff_note }}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                :disabled="processing"
                            >
                                {{ processing ? 'Saving…' : 'Save note' }}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
