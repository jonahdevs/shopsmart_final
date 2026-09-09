<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Pencil,
    ScrollText,
    Ticket,
    TriangleAlert,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { adminTones } from '@/components/admin/tones';
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
import {
    edit as adminCouponEdit,
    index as adminCoupons,
} from '@/routes/admin/coupons';
import { show as adminCustomer } from '@/routes/admin/customers';
import { show as adminOrder } from '@/routes/admin/orders';

const { detail } = defineProps<{
    detail: App.Data.AdminCouponDetailData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Coupons', href: adminCoupons().url },
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

/**
 * The counter and the rows are kept in step by Order::recordCouponUse(), which
 * increments only when the (coupon_id, order_id) unique index admitted a new
 * row. Surfacing the disagreement means a drift is visible rather than silent.
 */
const countersDisagree = computed(
    () => coupon.value.redemptionCount !== coupon.value.usedCount,
);

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
        value:
            coupon.value.usageLimit === null
                ? 'Unlimited'
                : String(coupon.value.usageLimit),
    },
    {
        label: 'Per customer',
        value:
            coupon.value.usageLimitPerUser === null
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
    <div class="flex flex-col gap-6">
        <Head :title="coupon.code" />

        <AdminPageHeader
            :title="coupon.code"
            :description="coupon.description ?? 'No internal note.'"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminCoupons()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Back to coupons
                    </Link>
                </Button>
                <Button size="sm" as-child>
                    <Link :href="adminCouponEdit(coupon.id)">
                        <Pencil class="size-4" aria-hidden="true" />
                        Edit
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 sm:grid-cols-2">
            <AdminStatCard
                label="Times redeemed"
                :value="String(coupon.usedCount)"
                :icon="Ticket"
                tone="brand"
                :hint="
                    countersDisagree
                        ? undefined
                        : `Matches ${coupon.redemptionCount} recorded redemption${coupon.redemptionCount === 1 ? '' : 's'}.`
                "
            />

            <AdminStatCard
                label="Discount given"
                :value="detail.discountedTotalFormatted"
                :icon="Wallet"
                tone="success"
                hint="Summed from the redemption rows."
            />
        </div>

        <AdminCard
            v-if="countersDisagree"
            padded
            class="flex items-start gap-3 text-sm"
        >
            <TriangleAlert
                class="mt-0.5 size-4 shrink-0"
                :class="adminTones.danger.text"
                aria-hidden="true"
            />
            <p>
                {{ coupon.redemptionCount }} redemption rows recorded — the
                counter and the history disagree.
            </p>
        </AdminCard>

        <!--
          The history is the record you read down; the terms and the removal
          control are what you glance at, so they sit in the aside.
        -->
        <div class="grid gap-6 lg:grid-cols-3">
            <AdminCard class="lg:col-span-2">
                <AdminCardHeader
                    title="Redemption history"
                    :icon="ScrollText"
                />

                <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                    Written once, when a payment confirms — never at checkout,
                    so an abandoned basket cannot eat a limited code's budget.
                </p>

                <AdminEmptyState
                    v-if="!hasRedemptions"
                    :icon="ScrollText"
                    title="Nobody has redeemed this code yet"
                />

                <!--
                  Wide content scrolls inside its own container so the page body
                  never scrolls sideways on a narrow screen.
                -->
                <div v-else class="overflow-x-auto">
                    <AdminTable>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Discount</TableHead>
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
                                        class="hover:text-primary transition-colors"
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
                                        v-if="
                                            use.customerId && canReadCustomers
                                        "
                                        :href="adminCustomer(use.customerId)"
                                        class="hover:text-primary transition-colors"
                                    >
                                        {{ use.customerName }}
                                    </Link>
                                    <span v-else>{{ use.customerName }}</span>
                                </TableCell>
                                <TableCell class="font-medium tabular-nums">
                                    {{ use.discountFormatted }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatIsoDate(use.redeemedAt) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </AdminTable>
                </div>
            </AdminCard>

            <div class="flex flex-col gap-6">
                <AdminCard>
                    <AdminCardHeader title="State" />

                    <div class="space-y-2 px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <AdminStatusBadge
                                :label="
                                    coupon.isRedeemable ? 'Live' : 'Not live'
                                "
                                :tone="
                                    coupon.isRedeemable ? 'success' : 'neutral'
                                "
                            />
                            <AdminStatusBadge
                                v-if="!coupon.isActive"
                                label="Switched off"
                                tone="danger"
                            />
                        </div>
                        <p class="text-muted-foreground text-xs">
                            {{ coupon.typeLabel }}
                        </p>
                    </div>
                </AdminCard>

                <AdminCard>
                    <AdminCardHeader title="Terms" :icon="Ticket" />

                    <p class="text-muted-foreground border-b px-5 py-3 text-xs">
                        What the checkout enforces when a shopper types this
                        code.
                    </p>

                    <dl
                        class="grid gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-1"
                    >
                        <div v-for="term in terms" :key="term.label">
                            <dt class="text-muted-foreground text-xs">
                                {{ term.label }}
                            </dt>
                            <dd class="mt-0.5 text-sm font-medium">
                                {{ term.value }}
                            </dd>
                        </div>
                    </dl>
                </AdminCard>
            </div>
        </div>

        <div class="flex justify-end">
            <Dialog v-model:open="confirmingRemoval">
                <DialogTrigger as-child>
                    <Button variant="destructive" size="sm">
                        {{
                            hasRedemptions
                                ? 'Deactivate coupon'
                                : 'Delete coupon'
                        }}
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
                                {{
                                    hasRedemptions
                                        ? 'Switch it off'
                                        : 'Delete it'
                                }}
                            </Button>
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
