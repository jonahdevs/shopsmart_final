<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
import { index as adminOrders, show as adminOrder } from '@/routes/admin/orders';

type OrderFilters = {
    search: string | null;
    status: string | null;
    payment_status: string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: string;
};

const { orders, pagination, filters, statusOptions, paymentStatusOptions } =
    defineProps<{
        orders: App.Data.AdminOrderRowData[];
        pagination: App.Data.PaginationData;
        filters: OrderFilters;
        statusOptions: { value: string; label: string }[];
        paymentStatusOptions: { value: string; label: string }[];
    }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Orders', href: '/admin/orders' },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter.
 */
const form = ref({
    search: filters.search ?? '',
    status: filters.status ?? '',
    payment_status: filters.payment_status ?? '',
    from: filters.from ?? '',
    to: filters.to ?? '',
});

/**
 * Only the filters that are actually set. Sending empty strings would put
 * `?status=` on every URL and make two identical views look like different
 * pages to the browser's history.
 */
function activeQuery(overrides: Record<string, string | number> = {}) {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (filters.sort !== 'placed_at' || filters.direction !== 'desc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    return { ...query, ...overrides };
}

let debounce: ReturnType<typeof setTimeout> | undefined;

/**
 * `replace` so typing in the search box does not push a history entry per
 * keystroke, and `preserveState` so the inputs keep focus and their values
 * across the visit — without it the Vue adapter re-keys the page and the box
 * you are typing in is unmounted mid-word.
 */
watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminOrders.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminOrders.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminOrders.url({
        query: activeQuery({ sort: column, direction }),
    });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Orders" />

        <AdminPageHeader
            title="Orders"
            :description="`${pagination.total} order${pagination.total === 1 ? '' : 's'} placed.`"
        />

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="order-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="order-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Order number, name or email"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="order-status">Status</Label>
                        <NativeSelect id="order-status" v-model="form.status">
                            <option value="">All statuses</option>
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="order-payment-status">Payment</Label>
                        <NativeSelect
                            id="order-payment-status"
                            v-model="form.payment_status"
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
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1.5">
                            <Label for="order-from">From</Label>
                            <Input
                                id="order-from"
                                v-model="form.from"
                                type="date"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="order-to">To</Label>
                            <Input
                                id="order-to"
                                v-model="form.to"
                                type="date"
                            />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="orders.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No orders match these filters.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :aria-sort="ariaSort('order_number')">
                                    <Link
                                        :href="sortHref('order_number')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Order
                                    </Link>
                                </TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Items</TableHead>
                                <TableHead :aria-sort="ariaSort('status')">
                                    <Link
                                        :href="sortHref('status')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Status
                                    </Link>
                                </TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead
                                    class="text-right"
                                    :aria-sort="ariaSort('total_cents')"
                                >
                                    <Link
                                        :href="sortHref('total_cents')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Total
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('placed_at')">
                                    <Link
                                        :href="sortHref('placed_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Placed
                                    </Link>
                                </TableHead>
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
                                        class="hover:underline"
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
                                <TableCell>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link
                                            :href="adminOrder(order.orderNumber)"
                                        >
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
            </CardContent>
        </Card>

        <AdminPagination
            :pagination="pagination"
            :href-for-page="hrefForPage"
        />
    </div>
</template>
