<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
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
    from: string | null;
    to: string | null;
    sort: string;
    direction: string;
};

const { orders, pagination, filters } = defineProps<{
    orders: App.Data.AdminOrderRowData[];
    pagination: App.Data.PaginationData;
    filters: OrderFilters;
    statusOptions: { value: string; label: string }[];
    paymentStatusOptions: { value: string; label: string }[];
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
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
    });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Orders" />

        <AdminPageHeader
            eyebrow="Sales"
            title="Orders"
            :description="`${pagination.total} order${pagination.total === 1 ? '' : 's'} placed.`"
        />

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
                <NativeSelect
                    v-model="form.status"
                    class="w-40"
                    aria-label="Status"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.payment_status"
                    class="w-40"
                    aria-label="Payment status"
                >
                    <option value="">All payments</option>
                    <option
                        v-for="option in paymentStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <Input
                    v-model="form.from"
                    type="date"
                    class="w-36"
                    aria-label="Placed from"
                />
                <Input
                    v-model="form.to"
                    type="date"
                    class="w-36"
                    aria-label="Placed to"
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
                <Table>
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
                                align="end"
                                class="text-right"
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
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
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
                </Table>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
