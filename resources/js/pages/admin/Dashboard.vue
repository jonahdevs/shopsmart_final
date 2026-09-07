<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import {
    Banknote,
    Boxes,
    ClipboardList,
    CreditCard,
    PackageCheck,
    Receipt,
    ShoppingBag,
    Star,
    TriangleAlert,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminChart from '@/components/admin/AdminChart.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import {
    compactMoneyFormatter,
    magnitudeBarOptions,
    shareBarOptions,
    sparklineOptions,
    timelineAreaOptions,
} from '@/lib/charts';
import { formatIsoDate } from '@/lib/utils';
import {
    index as adminOrders,
    show as adminOrder,
} from '@/routes/admin/orders';
import { index as adminProducts } from '@/routes/admin/products';
import { dashboard as adminDashboard } from '@/routes/admin';

const { stats, recentOrders, charts } = defineProps<{
    stats: App.Data.AdminDashboardStatsData;
    recentOrders: App.Data.AdminOrderRowData[];
    charts?: App.Data.AdminDashboardChartsData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: adminDashboard().url }],
    },
});

const { canAny } = usePermissions();

/*
  Four headline figures, each with its own trend. Every one carries a delta now:
  half of them used to pass null and print the period label instead, which read
  as "this metric has no trend" rather than "nobody worked it out".
*/
const tiles = computed(() => [
    {
        label: 'Revenue',
        value: stats.revenueFormatted,
        change: stats.revenueChangePercent,
        icon: Banknote,
        tone: 'success' as const,
        series: charts?.timeline.revenue ?? [],
        spark: 0,
    },
    {
        label: 'Paid orders',
        value: String(stats.paidOrderCount),
        change: stats.paidOrderChangePercent,
        icon: ShoppingBag,
        tone: 'info' as const,
        series: charts?.timeline.orders ?? [],
        spark: 2,
    },
    {
        label: 'Average order',
        value: stats.averageOrderValueFormatted,
        change: stats.averageOrderValueChangePercent,
        icon: Receipt,
        tone: 'accent' as const,
        series: [],
        spark: 4,
    },
    {
        label: 'New customers',
        value: String(stats.newCustomerCount),
        change: stats.newCustomerChangePercent,
        icon: UserRound,
        tone: 'brand' as const,
        series: charts?.timeline.customers ?? [],
        spark: 0,
    },
]);

/*
  Each queue counter is a filtered view of a real table, so each one links to
  it. A manager who reads "Low stock: 14" and cannot click through to the
  fourteen products has been told a number and denied the work.
*/
const queues = computed(() => [
    {
        label: 'Awaiting payment',
        value: stats.awaitingPaymentCount,
        icon: CreditCard,
        tone: 'warning' as const,
        href: adminOrders.url({ query: { payment_status: 'pending' } }),
        permissions: ['orders.view', 'orders.manage'],
    },
    {
        label: 'Awaiting fulfilment',
        value: stats.awaitingFulfilmentCount,
        icon: PackageCheck,
        tone: 'info' as const,
        href: adminOrders.url({ query: { status: 'processing' } }),
        permissions: ['orders.view', 'orders.manage'],
    },
    {
        label: 'Low stock',
        value: stats.lowStockCount,
        icon: TriangleAlert,
        tone: 'danger' as const,
        href: adminProducts.url({ query: { stock_status: 'low_stock' } }),
        permissions: ['products.view', 'products.manage'],
    },
]);

const visibleQueues = computed(() =>
    queues.value.filter((queue) => canAny(...queue.permissions)),
);

/*
  One measure at a time, on one axis.

  Revenue and orders share nothing but an x-axis, and giving each its own y-scale
  — which is what the old store's dashboard did — lets the two lines cross
  wherever the scales happen to place them and invites the reader to see a
  relationship that is an artefact of the axes. Switching measures says the same
  things honestly.
*/
type Measure = 'revenue' | 'orders' | 'customers';

const measure = ref<Measure>('revenue');

const measures: { key: Measure; label: string }[] = [
    { key: 'revenue', label: 'Revenue' },
    { key: 'orders', label: 'Paid orders' },
    { key: 'customers', label: 'New customers' },
];

const timelineSeries = computed(() => {
    if (!charts) {
        return [];
    }

    const values = {
        revenue: charts.timeline.revenue,
        orders: charts.timeline.orders,
        customers: charts.timeline.customers,
    }[measure.value];

    const name =
        measures.find((item) => item.key === measure.value)?.label ?? 'Revenue';

    return [{ name, data: values }];
});

const timelineOptions = computed(() => {
    if (!charts) {
        return {};
    }

    const money = compactMoneyFormatter(charts.timeline.currencySymbol);
    const label =
        measures.find((item) => item.key === measure.value)?.label ?? 'Revenue';

    return timelineAreaOptions(
        charts.timeline.labels,
        label,
        measure.value === 'revenue'
            ? money
            : (value: number): string => String(Math.round(value)),
    );
});

/** Turns a breakdown into the {labels, values, formatted} a bar chart needs. */
function breakdown(slices: App.Data.AdminChartSliceData[]) {
    return {
        labels: slices.map((slice) => slice.label),
        values: slices.map((slice) => slice.value),
        formatted: slices.map((slice) => slice.formatted),
    };
}

const statusChart = computed(() => breakdown(charts?.ordersByStatus ?? []));
const productChart = computed(() => breakdown(charts?.topProducts ?? []));
const categoryChart = computed(() => breakdown(charts?.topCategories ?? []));
const ratingChart = computed(() => breakdown(charts?.ratings ?? []));

/** The share bar wants one single-value series per slice, not one series of many. */
const methodSeries = computed(() =>
    (charts?.revenueByMethod ?? []).map((slice) => ({
        name: slice.label,
        data: [slice.value],
    })),
);

const methodFormatted = computed(() =>
    (charts?.revenueByMethod ?? []).map((slice) => slice.formatted),
);

const hasMethodRevenue = computed(() =>
    (charts?.revenueByMethod ?? []).some((slice) => slice.value > 0),
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Dashboard" />

        <AdminPageHeader
            eyebrow="Overview"
            title="Dashboard"
            :description="`How the store is trading — ${stats.periodLabel.toLowerCase()}.`"
        />

        <!-- Headline figures. -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :change="tile.change"
                :icon="tile.icon"
                :tone="tile.tone"
                :hint="tile.change === null ? stats.periodLabel : undefined"
            >
                <template v-if="tile.series.length > 0" #spark>
                    <AdminChart
                        :options="sparklineOptions(tile.spark)"
                        :series="[{ name: tile.label, data: tile.series }]"
                        :height="48"
                    />
                </template>
            </AdminStatCard>
        </div>

        <!-- Work waiting to be done. -->
        <div v-if="visibleQueues.length" class="grid gap-4 sm:grid-cols-3">
            <AdminStatCard
                v-for="queue in visibleQueues"
                :key="queue.label"
                :label="queue.label"
                :value="String(queue.value)"
                :icon="queue.icon"
                :tone="queue.tone"
                :href="queue.href"
                hint="View list"
            />
        </div>

        <Deferred data="charts">
            <template #fallback>
                <div class="grid gap-4 lg:grid-cols-3">
                    <Skeleton class="h-[380px] lg:col-span-2" />
                    <Skeleton class="h-[380px]" />
                    <Skeleton class="h-[340px]" />
                    <Skeleton class="h-[340px]" />
                    <Skeleton class="h-[340px]" />
                </div>
            </template>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- The trading line. -->
                <AdminCard class="lg:col-span-2">
                    <AdminCardHeader title="Trading" :icon="ClipboardList">
                        <template #actions>
                            <div
                                class="bg-muted flex items-center gap-0.5 rounded-md p-0.5"
                                role="group"
                                aria-label="Measure"
                            >
                                <button
                                    v-for="item in measures"
                                    :key="item.key"
                                    type="button"
                                    class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        measure === item.key
                                            ? 'bg-card text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    :aria-pressed="measure === item.key"
                                    @click="measure = item.key"
                                >
                                    {{ item.label }}
                                </button>
                            </div>
                        </template>
                    </AdminCardHeader>

                    <div class="p-2">
                        <AdminChart
                            :options="timelineOptions"
                            :series="timelineSeries"
                            :height="300"
                        />
                    </div>
                </AdminCard>

                <!-- How the money arrived. -->
                <AdminCard>
                    <AdminCardHeader
                        title="Revenue by method"
                        :icon="CreditCard"
                    />

                    <div v-if="hasMethodRevenue" class="p-4">
                        <AdminChart
                            :options="shareBarOptions(methodFormatted)"
                            :series="methodSeries"
                            :height="150"
                        />
                    </div>
                    <AdminEmptyState
                        v-else
                        :icon="CreditCard"
                        title="No payments captured"
                        description="Revenue splits by payment method once orders are paid."
                    />
                </AdminCard>

                <!-- Where the orders are sitting. -->
                <AdminCard>
                    <AdminCardHeader
                        title="Orders by status"
                        :icon="ShoppingBag"
                    />
                    <div class="p-2">
                        <AdminChart
                            :options="
                                magnitudeBarOptions(
                                    statusChart.labels,
                                    statusChart.formatted,
                                )
                            "
                            :series="[
                                { name: 'Orders', data: statusChart.values },
                            ]"
                            :height="260"
                        />
                    </div>
                </AdminCard>

                <!-- What is selling. -->
                <AdminCard>
                    <AdminCardHeader title="Top products" :icon="Boxes" />

                    <div v-if="productChart.values.length" class="p-2">
                        <AdminChart
                            :options="
                                magnitudeBarOptions(
                                    productChart.labels,
                                    productChart.formatted,
                                )
                            "
                            :series="[
                                { name: 'Units', data: productChart.values },
                            ]"
                            :height="260"
                        />
                    </div>
                    <AdminEmptyState
                        v-else
                        :icon="Boxes"
                        title="Nothing sold yet"
                        description="Best sellers appear here once orders are placed."
                    />
                </AdminCard>

                <!--
                  Categories, not products. A product in three categories counts
                  its units in all three, so these sum to more than the order
                  total — the header says "aisles" rather than "sales" for that
                  reason.
                -->
                <AdminCard>
                    <AdminCardHeader title="Busiest aisles" :icon="Boxes" />

                    <div v-if="categoryChart.values.length" class="p-2">
                        <AdminChart
                            :options="
                                magnitudeBarOptions(
                                    categoryChart.labels,
                                    categoryChart.formatted,
                                )
                            "
                            :series="[
                                { name: 'Units', data: categoryChart.values },
                            ]"
                            :height="260"
                        />
                    </div>
                    <AdminEmptyState
                        v-else
                        :icon="Boxes"
                        title="No category sales yet"
                        description="Units sold are counted once per category a product sits in."
                    />
                </AdminCard>

                <!-- Reputation. Lifetime, not windowed — see the controller. -->
                <AdminCard>
                    <AdminCardHeader title="Review ratings" :icon="Star" />

                    <div
                        v-if="charts && charts.reviewCount > 0"
                        class="flex flex-col gap-2 p-2"
                    >
                        <div class="flex items-baseline gap-2 px-3 pt-2">
                            <span
                                class="font-display text-3xl font-extrabold tabular-nums"
                            >
                                {{ charts.averageRating }}
                            </span>
                            <span class="text-muted-foreground text-xs">
                                of 5 · {{ charts.reviewCount }} approved reviews
                            </span>
                        </div>

                        <AdminChart
                            :options="
                                magnitudeBarOptions(
                                    ratingChart.labels,
                                    ratingChart.formatted,
                                )
                            "
                            :series="[
                                { name: 'Reviews', data: ratingChart.values },
                            ]"
                            :height="200"
                        />
                    </div>
                    <AdminEmptyState
                        v-else
                        :icon="Star"
                        title="No approved reviews"
                        description="Approve reviews in the moderation queue to see the spread."
                    />
                </AdminCard>
            </div>
        </Deferred>

        <AdminCard v-if="canAny('orders.view', 'orders.manage')">
            <AdminCardHeader title="Recent orders" :icon="ShoppingBag">
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminOrders()">View all</Link>
                    </Button>
                </template>
            </AdminCardHeader>

            <AdminEmptyState
                v-if="recentOrders.length === 0"
                :icon="ShoppingBag"
                title="No orders yet"
                description="Orders placed on the storefront land here."
            />

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
                        <TableRow v-for="order in recentOrders" :key="order.id">
                            <TableCell class="font-medium">
                                <Link
                                    :href="adminOrder(order.orderNumber)"
                                    class="hover:text-primary transition-colors"
                                >
                                    {{ order.orderNumber }}
                                </Link>
                            </TableCell>
                            <TableCell class="max-w-48 truncate">
                                {{ order.customerName }}
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
                                    :variant="order.paymentStatusVariant"
                                />
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
        </AdminCard>
    </div>
</template>
