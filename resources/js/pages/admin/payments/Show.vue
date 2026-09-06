<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
import { show as adminOrder } from '@/routes/admin/orders';
import { index as adminPayments } from '@/routes/admin/payments';

const { payment } = defineProps<{
    payment: App.Data.AdminPaymentRowData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Payments', href: '/admin/payments' },
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
    <div class="flex flex-col gap-6 p-4">
        <Head :title="`Payment ${payment.reference}`" />

        <AdminPageHeader
            title="Payment"
            :description="payment.reference"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminPayments()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        All payments
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div v-for="row in rows" :key="row.label">
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="font-medium break-all">
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="payment.failureReason"
                        class="border-destructive/30 bg-destructive/5 mt-6 rounded-lg border p-4"
                    >
                        <p class="text-sm font-medium">Why it failed</p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ payment.failureReason }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Amount</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="text-2xl font-semibold tabular-nums">
                            {{ payment.amountFormatted }}
                        </p>
                        <Badge :variant="toBadgeVariant(payment.statusVariant)">
                            {{ payment.statusLabel }}
                        </Badge>
                        <p class="text-muted-foreground pt-2 text-xs">
                            Frozen when the attempt was created. A verification
                            is checked against this, never the live order total.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="payment.orderNumber">
                    <CardHeader>
                        <CardTitle>Order</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="adminOrder(payment.orderNumber)">
                                {{ payment.orderNumber }}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
