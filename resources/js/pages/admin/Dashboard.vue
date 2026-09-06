<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { index as adminOrders, show as adminOrder } from '@/routes/admin/orders';

const { stats, recentOrders } = defineProps<{
    stats: App.Data.AdminDashboardStatsData;
    recentOrders: App.Data.AdminOrderRowData[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: '/admin' }] },
});

const { canAny } = usePermissions();

const tiles = [
    {
        label: 'Revenue',
        value: stats.revenueFormatted,
        change: stats.revenueChangePercent,
        hint: stats.periodLabel,
    },
    {
        label: 'Paid orders',
        value: String(stats.paidOrderCount),
        change: stats.paidOrderChangePercent,
        hint: stats.periodLabel,
    },
    {
        label: 'Average order',
        value: stats.averageOrderValueFormatted,
        change: null,
        hint: stats.periodLabel,
    },
    {
        label: 'New customers',
        value: String(stats.newCustomerCount),
        change: null,
        hint: stats.periodLabel,
    },
];

const queues = [
    { label: 'Awaiting payment', value: stats.awaitingPaymentCount },
    { label: 'Awaiting fulfilment', value: stats.awaitingFulfilmentCount },
    { label: 'Low stock', value: stats.lowStockCount },
];

</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Dashboard" />

        <AdminPageHeader
            title="Dashboard"
            description="How the store is trading."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="tile in tiles" :key="tile.label">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-muted-foreground text-sm font-medium"
                    >
                        {{ tile.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1">
                    <p class="text-2xl font-semibold tracking-tight">
                        {{ tile.value }}
                    </p>
                    <p
                        v-if="tile.change !== null && tile.change !== undefined"
                        class="flex items-center gap-1 text-xs"
                        :class="
                            tile.change >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-destructive'
                        "
                    >
                        <component
                            :is="tile.change >= 0 ? TrendingUp : TrendingDown"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                        {{ Math.abs(tile.change) }}% vs previous period
                    </p>
                    <p v-else class="text-muted-foreground text-xs">
                        {{ tile.hint }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card v-for="queue in queues" :key="queue.label">
                <CardContent class="flex items-center justify-between pt-6">
                    <span class="text-sm font-medium">{{ queue.label }}</span>
                    <span class="text-xl font-semibold tabular-nums">
                        {{ queue.value }}
                    </span>
                </CardContent>
            </Card>
        </div>

        <Card v-if="canAny('orders.view', 'orders.manage')">
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle>Recent orders</CardTitle>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminOrders()">View all</Link>
                </Button>
            </CardHeader>
            <CardContent>
                <p
                    v-if="recentOrders.length === 0"
                    class="text-muted-foreground py-6 text-center text-sm"
                >
                    No orders yet.
                </p>

                <!--
                  Wide content scrolls inside its own container so the page body
                  never scrolls sideways on a narrow screen.
                -->
                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead>Placed</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="order in recentOrders"
                                :key="order.id"
                            >
                                <TableCell class="font-medium">
                                    <Link
                                        :href="adminOrder(order.orderNumber)"
                                        class="hover:underline"
                                    >
                                        {{ order.orderNumber }}
                                    </Link>
                                </TableCell>
                                <TableCell class="max-w-48 truncate">
                                    {{ order.customerName }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="toBadgeVariant(order.statusVariant)">
                                        {{ order.statusLabel }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="toBadgeVariant(order.paymentStatusVariant)"
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
    </div>
</template>
