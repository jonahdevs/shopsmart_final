<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowLeftRight,
    Banknote,
    CreditCard,
    Download,
    MessageSquare,
    Package,
    StickyNote,
    Truck,
    User,
} from '@lucide/vue';
import {
    updateNote,
    updateStatus,
} from '@/actions/App/Http/Controllers/Admin/OrderController';
import OrderInvoiceController from '@/actions/App/Http/Controllers/Admin/OrderInvoiceController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
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
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminOrders } from '@/routes/admin/orders';

const { detail } = defineProps<{
    detail: App.Data.AdminOrderDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Orders', href: adminOrders().url },
        ],
    },
});

const { can } = usePermissions();

const order = detail.order;
const totals = order.totals;
const address = order.shippingAddress;
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="`Order ${order.orderNumber}`" />

        <AdminPageHeader
            eyebrow="Sales"
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

                <!--
                  A plain anchor, not a <Link>: the invoice is a PDF the browser
                  downloads, and an Inertia visit would ask the server for a page
                  and get a file it cannot render.
                -->
                <Button v-if="can('orders.view')" size="sm" as-child>
                    <a :href="OrderInvoiceController.url(order.orderNumber)">
                        <Download class="size-4" aria-hidden="true" />
                        Invoice
                    </a>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <AdminStatusBadge
                :label="order.statusLabel"
                :variant="order.statusVariant"
            />
            <AdminStatusBadge
                :label="order.paymentStatusLabel"
                :variant="order.paymentStatusVariant"
            />
            <span
                v-if="order.paymentMethod"
                class="text-muted-foreground text-sm"
            >
                via {{ order.paymentMethod }}
            </span>
            <span
                v-if="detail.stockDeducted"
                class="text-muted-foreground text-sm"
            >
                · stock taken
            </span>
        </div>

        <!--
          The three figures a staff member is asked about on the phone. The
          full breakdown stays under the items table, where the arithmetic can
          be checked line by line.
        -->
        <div class="grid gap-4 sm:grid-cols-3">
            <AdminStatCard
                label="Order total"
                tone="brand"
                :value="totals.totalFormatted"
                :icon="Banknote"
                :hint="
                    totals.couponCode
                        ? `Coupon ${totals.couponCode}`
                        : undefined
                "
            />
            <AdminStatCard
                label="Items"
                :value="String(order.itemCount)"
                :icon="Package"
                :hint="`${order.lines.length} line${order.lines.length === 1 ? '' : 's'}`"
            />
            <AdminStatCard
                label="Delivery"
                tone="info"
                :value="totals.shippingFormatted"
                :icon="Truck"
                :hint="totals.shippingIsFree ? 'Free delivery' : undefined"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <AdminCard>
                    <AdminCardHeader title="Items" :icon="Package" />

                    <!--
                      Wide content scrolls inside its own container so the page
                      body never scrolls sideways on a narrow screen.
                    -->
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
                                    <TableCell class="text-right tabular-nums">
                                        {{ line.unitPriceFormatted }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
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

                    <div class="flex justify-end border-t px-5 py-4">
                        <dl class="w-full max-w-xs space-y-1.5 text-sm">
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
                            <div
                                class="flex justify-between border-t pt-2 font-semibold"
                            >
                                <dt>Total</dt>
                                <dd class="tabular-nums">
                                    {{ totals.totalFormatted }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </AdminCard>

                <AdminCard>
                    <AdminCardHeader title="Payments" :icon="CreditCard" />

                    <p
                        v-if="detail.payments.length === 0"
                        class="text-muted-foreground px-5 py-4 text-sm"
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
                                        <AdminStatusBadge
                                            :label="payment.statusLabel"
                                            :variant="payment.statusVariant"
                                        />
                                        <span
                                            v-if="payment.failureReason"
                                            class="text-muted-foreground block text-xs"
                                        >
                                            {{ payment.failureReason }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ payment.amountFormatted }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ formatIsoDate(payment.createdAt) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </AdminCard>
            </div>

            <div class="flex flex-col gap-6">
                <AdminCard>
                    <AdminCardHeader title="Customer" :icon="User" />

                    <div class="space-y-1 px-5 py-4 text-sm">
                        <p class="font-medium">{{ order.customerName }}</p>
                        <p class="text-muted-foreground break-all">
                            {{ order.customerEmail }}
                        </p>
                        <p
                            v-if="order.customerPhone"
                            class="text-muted-foreground"
                        >
                            {{ order.customerPhone }}
                        </p>
                        <p
                            v-if="detail.customerId === null"
                            class="text-muted-foreground pt-2 text-xs"
                        >
                            This account has since been deleted. The order keeps
                            its own record of who placed it.
                        </p>
                    </div>
                </AdminCard>

                <AdminCard v-if="address">
                    <AdminCardHeader title="Delivery" :icon="Truck" />

                    <div class="space-y-0.5 px-5 py-4 text-sm">
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
                    </div>
                </AdminCard>

                <AdminCard v-if="order.customerNote">
                    <AdminCardHeader
                        title="Customer note"
                        :icon="MessageSquare"
                    />

                    <p class="px-5 py-4 text-sm whitespace-pre-line">
                        {{ order.customerNote }}
                    </p>
                </AdminCard>

                <AdminCard v-if="can('orders.manage')">
                    <AdminCardHeader
                        title="Move status"
                        :icon="ArrowLeftRight"
                    />

                    <div class="px-5 py-4">
                        <p
                            v-if="detail.availableStatuses.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            This order has reached a final status and cannot be
                            moved again.
                        </p>

                        <Form
                            v-else
                            v-bind="updateStatus.form(order.orderNumber)"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                            class="space-y-3"
                        >
                            <div class="space-y-1.5">
                                <Label for="order-status">New status</Label>
                                <!--
                                  The current status leads the list and is
                                  selected, so the picker opens showing the truth
                                  and a staff member has to choose a move rather
                                  than stumble into one. Which moves are offered
                                  is `detail.availableStatuses`, decided by the
                                  server — the lifecycle is not restated here.
                                -->
                                <NativeSelect
                                    id="order-status"
                                    name="status"
                                    :model-value="order.status"
                                >
                                    <NativeSelectOption :value="order.status">
                                        {{ order.statusLabel }} (current)
                                    </NativeSelectOption>
                                    <NativeSelectOption
                                        v-for="option in detail.availableStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </NativeSelectOption>
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
                    </div>
                </AdminCard>

                <AdminCard v-if="can('orders.manage')">
                    <AdminCardHeader title="Internal note" :icon="StickyNote" />

                    <div class="px-5 py-4">
                        <Form
                            v-bind="updateNote.form(order.orderNumber)"
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
                    </div>
                </AdminCard>
            </div>
        </div>
    </div>
</template>
