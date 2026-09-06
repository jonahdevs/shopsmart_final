<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate } from '@/lib/utils';
import { show as adminCustomer } from '@/routes/admin/customers';
import { edit as adminCouponEdit, index as adminCoupons } from '@/routes/admin/coupons';
import { show as adminOrder } from '@/routes/admin/orders';

const { detail } = defineProps<{
    detail: App.Data.AdminCouponDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Coupons', href: '/admin/coupons' },
        ],
    },
});

const coupon = computed(() => detail.coupon);

/** True while the confirm-removal dialog is open. */
const confirmingRemoval = ref(false);

/**
 * The history links out to two other admin sections, each behind its own
 * permission. Marketing and sales are not necessarily the same person, so a
 * link only appears for someone the server would actually let through.
 */
const { can } = usePermissions();
const canReadCustomers = computed(() => can('customers.view'));
const canReadOrders = computed(() => can('orders.view'));

/**
 * A code that has been redeemed is deactivated rather than deleted:
 * `coupon_uses` cascades on delete, so removing the row would take the
 * redemption history with it. The button says which of the two will happen.
 */
const hasRedemptions = computed(() => detail.redemptions.length > 0);

const terms = computed(() => [
    { label: 'Discount', value: coupon.value.valueLabel },
    {
        label: 'Minimum spend',
        value:
            coupon.value.minSubtotalCents > 0
                ? coupon.value.minSubtotalFormatted
                : 'None',
    },
    {
        label: 'Maximum discount',
        value: coupon.value.maxDiscountFormatted ?? 'Uncapped',
    },
    {
        label: 'Total redemptions allowed',
        value: coupon.value.usageLimit === null
            ? 'Unlimited'
            : String(coupon.value.usageLimit),
    },
    {
        label: 'Per customer',
        value: coupon.value.usageLimitPerUser === null
            ? 'Unlimited'
            : String(coupon.value.usageLimitPerUser),
    },
    {
        label: 'Valid from',
        value: coupon.value.startsAt
            ? formatIsoDate(coupon.value.startsAt)
            : 'Immediately',
    },
    {
        label: 'Valid until',
        value: coupon.value.expiresAt
            ? formatIsoDate(coupon.value.expiresAt)
            : 'No end date',
    },
]);
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="coupon.code" />

        <AdminPageHeader
            :title="coupon.code"
            :description="coupon.description ?? 'No internal note.'"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupons()">Back to coupons</Link>
                </Button>
                <Button size="sm" as-child>
                    <Link :href="adminCouponEdit(coupon.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Edit
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">State</p>
                    <p class="mt-2">
                        <Badge
                            :variant="coupon.isRedeemable ? 'default' : 'outline'"
                        >
                            {{ coupon.isRedeemable ? 'Live' : 'Not live' }}
                        </Badge>
                        <Badge v-if="!coupon.isActive" variant="destructive" class="ml-2">
                            Switched off
                        </Badge>
                    </p>
                    <p class="text-muted-foreground mt-2 text-xs">
                        {{ coupon.typeLabel }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Times redeemed</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ coupon.usedCount }}
                    </p>
                    <!--
                      The counter and the rows are kept in step by
                      Order::recordCouponUse(), which increments only when the
                      (coupon_id, order_id) unique index admitted a new row.
                      Showing both means a drift is visible rather than silent.
                    -->
                    <p
                        v-if="coupon.redemptionCount !== coupon.usedCount"
                        class="mt-1 text-xs text-red-600 dark:text-red-500"
                    >
                        {{ coupon.redemptionCount }} redemption rows recorded —
                        the counter and the history disagree.
                    </p>
                    <p v-else class="text-muted-foreground mt-1 text-xs">
                        Matches {{ coupon.redemptionCount }} recorded
                        redemption{{ coupon.redemptionCount === 1 ? '' : 's' }}.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <p class="text-muted-foreground text-sm">Discount given</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ detail.discountedTotalFormatted }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Summed from the redemption rows.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Terms</CardTitle>
                <CardDescription>
                    What the checkout enforces when a shopper types this code.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="term in terms" :key="term.label">
                        <dt class="text-muted-foreground text-xs">
                            {{ term.label }}
                        </dt>
                        <dd class="mt-0.5 text-sm font-medium">
                            {{ term.value }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Redemption history</CardTitle>
                <CardDescription>
                    Written once, when a payment confirms — never at checkout,
                    so an abandoned basket cannot eat a limited code's budget.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!hasRedemptions"
                    class="text-muted-foreground py-6 text-center text-sm"
                >
                    Nobody has redeemed this code yet.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead class="text-right">
                                    Discount
                                </TableHead>
                                <TableHead>Redeemed</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="use in detail.redemptions"
                                :key="use.id"
                            >
                                <TableCell class="font-medium">
                                    <Link
                                        v-if="use.orderNumber && canReadOrders"
                                        :href="adminOrder(use.orderNumber)"
                                        class="hover:underline"
                                    >
                                        {{ use.orderNumber }}
                                    </Link>
                                    <span v-else-if="use.orderNumber">
                                        {{ use.orderNumber }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        Order removed
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <!--
                                      Named from the order's frozen
                                      customer_name; a null customerId just
                                      means the account has since been closed.
                                    -->
                                    <Link
                                        v-if="use.customerId && canReadCustomers"
                                        :href="adminCustomer(use.customerId)"
                                        class="hover:underline"
                                    >
                                        {{ use.customerName }}
                                    </Link>
                                    <span v-else>{{ use.customerName }}</span>
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    {{ use.discountFormatted }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatIsoDate(use.redeemedAt) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Dialog v-model:open="confirmingRemoval">
                <DialogTrigger as-child>
                    <Button variant="destructive" size="sm">
                        {{ hasRedemptions ? 'Deactivate coupon' : 'Delete coupon' }}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                hasRedemptions
                                    ? `Switch off ${coupon.code}?`
                                    : `Delete ${coupon.code}?`
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            <template v-if="hasRedemptions">
                                This code has been redeemed, so it is switched
                                off rather than removed — deleting it would take
                                the redemption history with it. No further
                                shopper can use it.
                            </template>
                            <template v-else>
                                Nobody has used this code, so it can be removed
                                outright. This cannot be undone.
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <DialogClose as-child>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Form
                            v-bind="CouponController.destroy.form(coupon.id)"
                            @success="confirmingRemoval = false"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                {{ hasRedemptions ? 'Switch it off' : 'Delete it' }}
                            </Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
