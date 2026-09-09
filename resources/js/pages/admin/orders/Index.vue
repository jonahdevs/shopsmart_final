<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Banknote,
    CreditCard,
    Package,
    PackageCheck,
    Receipt,
} from '@lucide/vue';
import { computed } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminDateRangePicker from '@/components/admin/AdminDateRangePicker.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
import { SelectItem } from '@/components/ui/select';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as adminOrders,
    show as adminOrder,
} from '@/routes/admin/orders';

type OrderFilters = {
    search: string | null;
    status: string | null;
    payment_status: string | null;
    range: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: string;
};

const { orders, pagination, filters, dateRange, stats } = defineProps<{
    orders: App.Data.AdminOrderRowData[];
    pagination: App.Data.PaginationData;
    filters: OrderFilters;
    /** The resolved window and the presets the picker offers. */
    dateRange: App.Data.AdminDateRangeData;
    statusOptions: { value: string; label: string }[];
    paymentStatusOptions: { value: string; label: string }[];
    stats: App.Data.AdminOrderStatsData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Orders', href: adminOrders().url },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter. `useIndexTable` owns the debounce, the visit options and
 * the rule that empty filters are omitted rather than sent blank.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminOrders.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'placed_at', direction: 'desc' },
        fields: {
            search: filters.search ?? '',
            status: filters.status ?? '',
            payment_status: filters.payment_status ?? '',
            range: filters.range ?? '',
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
    });

/*
  A preset writes only its key to the URL; a drawn window writes only its two
  dates. Never both, or a shared "last 30 days" link would carry the dates it
  resolved to on the day it was sent and stop moving.

  All three land in one assignment, so `useIndexTable`'s deep watcher turns the
  change into a single debounced visit rather than three.
*/
function setRange(preset: string, rangeFrom: string, rangeTo: string): void {
    form.value = {
        ...form.value,
        range: preset,
        from: preset === 'custom' ? rangeFrom : '',
        to: preset === 'custom' ? rangeTo : '',
    };
}

/*
  What the calendar highlights. A drawn window is whatever is in the form; a
  preset is whatever the server resolved it to, because only the server knows
  where "this month" starts.
*/
const pickedFrom = computed(() =>
    form.value.range === 'custom' ? form.value.from : (dateRange.start ?? ''),
);

const pickedTo = computed(() =>
    form.value.range === 'custom' ? form.value.to : (dateRange.end ?? ''),
);

/*
  The two queue tiles are filters of this very table, so each one is typed
  against the generated enum: a status renamed on the server then fails the
  build here rather than silently opening an empty list.
*/
const AWAITING_PAYMENT: App.Enums.PaymentStatus = 'pending';
const AWAITING_FULFILMENT: App.Enums.OrderStatus = 'processing';

/*
  Two queues and two trading figures. The queues carry the filter that produces
  them — a count staff cannot click through to is a number they have been told
  and denied the work behind. The trading pair carries a delta instead, and
  names its window every time, because nothing else on this page says which
  thirty days the money belongs to.
*/
const tiles = computed(() => [
    {
        label: 'Awaiting payment',
        value: String(stats.awaitingPaymentCount),
        icon: CreditCard,
        tone: 'warning' as const,
        href: adminOrders.url({
            query: { payment_status: AWAITING_PAYMENT },
        }),
        hint: 'View list',
        change: null,
    },
    {
        label: 'Being packed',
        value: String(stats.awaitingFulfilmentCount),
        icon: PackageCheck,
        tone: 'info' as const,
        href: adminOrders.url({ query: { status: AWAITING_FULFILMENT } }),
        hint: 'View list',
        change: null,
    },
    {
        label: 'Revenue',
        value: stats.revenueFormatted,
        icon: Banknote,
        tone: 'success' as const,
        href: undefined,
        hint: stats.periodLabel,
        change: stats.revenueChangePercent,
    },
    {
        label: 'Average order',
        value: stats.averageOrderValueFormatted,
        icon: Receipt,
        tone: 'accent' as const,
        href: undefined,
        hint: stats.periodLabel,
        change: stats.averageOrderValueChangePercent,
    },
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Orders" />

        <AdminPageHeader
            title="Orders"
            :description="`${pagination.total} order${pagination.total === 1 ? '' : 's'} placed.`"
        />

        <!--
          The store, not the filtered page below. These figures are the context
          a staff member filters against, so they hold still while the table
          moves.
        -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :icon="tile.icon"
                :tone="tile.tone"
                :href="tile.href"
                :hint="tile.hint"
                :change="tile.change"
            />
        </div>

        <!--
          One card, four strips: filters, table, pagination. Not three cards —
          they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Order number, name or email"
                search-label="Search orders"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.status"
                    class="w-40"
                    label="Status"
                    all-label="All statuses"
                >
                    <SelectItem
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.payment_status"
                    class="w-40"
                    label="Payment status"
                    all-label="All payments"
                >
                    <SelectItem
                        v-for="option in paymentStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminDateRangePicker
                    :presets="dateRange.presets"
                    :preset="form.range"
                    :from="pickedFrom"
                    :to="pickedTo"
                    clearable
                    aria-label="Filter by when the order was placed"
                    @change="setRange"
                />
            </AdminFilterBar>

            <AdminEmptyState
                v-if="orders.length === 0"
                :icon="Package"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching orders' : 'No orders yet'"
                :description="
                    isFiltered
                        ? 'No orders match these filters.'
                        : 'Orders placed on the storefront land here.'
                "
            />

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <AdminTable>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Order"
                                :href="sortHref('order_number')"
                                :sort="ariaSort('order_number')"
                            />
                            <TableHead>Customer</TableHead>
                            <TableHead>Items</TableHead>
                            <AdminSortableHead
                                label="Status"
                                :href="sortHref('status')"
                                :sort="ariaSort('status')"
                            />
                            <TableHead>Payment</TableHead>
                            <AdminSortableHead
                                label="Total"
                                :href="sortHref('total_cents')"
                                :sort="ariaSort('total_cents')"
                            />
                            <AdminSortableHead
                                label="Placed"
                                :href="sortHref('placed_at')"
                                :sort="ariaSort('placed_at')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="order in orders" :key="order.id">
                            <TableCell class="font-medium">
                                <Link
                                    :href="adminOrder(order.orderNumber)"
                                    class="hover:text-primary transition-colors"
                                >
                                    {{ order.orderNumber }}
                                </Link>
                            </TableCell>
                            <TableCell class="max-w-56">
                                <span class="block truncate">
                                    {{ order.customerName }}
                                </span>
                                <span
                                    class="text-muted-foreground block truncate text-xs"
                                >
                                    {{ order.customerEmail }}
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
                                    :variant="order.paymentStatusVariant"
                                />
                            </TableCell>
                            <TableCell class="font-medium tabular-nums">
                                {{ order.totalFormatted }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatIsoDate(order.placedAt) }}
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="adminOrder(order.orderNumber)">
                                        View
                                        <span class="sr-only">
                                            order {{ order.orderNumber }}
                                        </span>
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </AdminTable>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
