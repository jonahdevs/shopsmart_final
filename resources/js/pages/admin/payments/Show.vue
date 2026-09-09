<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Package, TriangleAlert, Wallet } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as adminOrder } from '@/routes/admin/orders';
import { index as adminPayments } from '@/routes/admin/payments';

const { payment } = defineProps<{
    payment: App.Data.AdminPaymentRowData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Payments', href: adminPayments().url },
        ],
    },
});

/**
 * The gateway's raw response is deliberately absent from this page. It is
 * encrypted at rest because it carries the payer's name, phone and masked
 * instrument; `failureReason` is the part of it staff actually need.
 */
const rows = [
    { label: 'Gateway', value: payment.gateway },
    { label: 'Channel', value: payment.channel ?? '—' },
    { label: 'Currency', value: payment.currency },
    { label: 'Gateway reference', value: payment.gatewayReference ?? '—' },
    { label: 'Attempted', value: formatIsoDate(payment.createdAt) },
    {
        label: 'Settled',
        value: payment.paidAt ? formatIsoDate(payment.paidAt) : '—',
    },
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="`Payment ${payment.reference}`" />

        <AdminPageHeader title="Payment" :description="payment.reference">
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminPayments()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        All payments
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <AdminCard>
                    <AdminCardHeader title="Details" :icon="Wallet" />

                    <dl class="grid gap-3 px-5 py-4 text-sm sm:grid-cols-2">
                        <div v-for="row in rows" :key="row.label">
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="font-medium break-all">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                </AdminCard>

                <!--
                  A failure gets its own panel rather than a footnote inside
                  Details: it is the reason the page was opened at all.
                -->
                <AdminCard
                    v-if="payment.failureReason"
                    class="border-destructive/30"
                >
                    <AdminCardHeader
                        title="Why it failed"
                        :icon="TriangleAlert"
                    />

                    <p class="text-muted-foreground px-5 py-4 text-sm">
                        {{ payment.failureReason }}
                    </p>
                </AdminCard>
            </div>

            <div class="flex flex-col gap-6">
                <AdminCard>
                    <AdminCardHeader title="Amount" />

                    <div class="space-y-2 px-5 py-4">
                        <p
                            class="font-display text-2xl font-extrabold tracking-[-0.02em] tabular-nums"
                        >
                            {{ payment.amountFormatted }}
                        </p>
                        <AdminStatusBadge
                            :label="payment.statusLabel"
                            :variant="payment.statusVariant"
                        />
                        <p class="text-muted-foreground pt-2 text-xs">
                            Frozen when the attempt was created. A verification
                            is checked against this, never the live order total.
                        </p>
                    </div>
                </AdminCard>

                <AdminCard v-if="payment.orderNumber">
                    <AdminCardHeader title="Order" :icon="Package" />

                    <div class="px-5 py-4">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="adminOrder(payment.orderNumber)">
                                {{ payment.orderNumber }}
                            </Link>
                        </Button>
                    </div>
                </AdminCard>
            </div>
        </div>
    </div>
</template>
