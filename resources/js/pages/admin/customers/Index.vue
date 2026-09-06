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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatIsoDate } from '@/lib/utils';
import {
    index as adminCustomers,
    show as adminCustomer,
} from '@/routes/admin/customers';

type CustomerFilters = {
    search: string | null;
    sort: string;
    direction: string;
};

const { customers, pagination, filters } = defineProps<{
    customers: App.Data.AdminCustomerRowData[];
    pagination: App.Data.PaginationData;
    filters: CustomerFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Customers', href: '/admin/customers' },
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
});

/**
 * Only the filters that are actually set. Sending empty strings would put
 * `?search=` on every URL and make two identical views look like different
 * pages to the browser's history.
 */
function activeQuery(overrides: Record<string, string | number> = {}) {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (filters.sort !== 'created_at' || filters.direction !== 'desc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    return { ...query, ...overrides };
}

let debounce: ReturnType<typeof setTimeout> | undefined;

/**
 * `replace` so typing in the search box does not push a history entry per
 * keystroke, and `preserveState` so the input keeps focus and its value across
 * the visit — without it the Vue adapter re-keys the page and the box you are
 * typing in is unmounted mid-word.
 */
watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminCustomers.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminCustomers.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminCustomers.url({
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
        <Head title="Customers" />

        <AdminPageHeader
            title="Customers"
            :description="`${pagination.total} registered customer${pagination.total === 1 ? '' : 's'}.`"
        />

        <Card>
            <CardContent class="pt-6">
                <div class="space-y-1.5 sm:max-w-sm">
                    <Label for="customer-search">Search</Label>
                    <div class="relative">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <Input
                            id="customer-search"
                            v-model="form.search"
                            class="pl-8"
                            placeholder="Name or email"
                            type="search"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="customers.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No customers match this search.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :aria-sort="ariaSort('name')">
                                    <Link
                                        :href="sortHref('name')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Customer
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('email')">
                                    <Link
                                        :href="sortHref('email')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Email
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('orders_count')">
                                    <Link
                                        :href="sortHref('orders_count')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Orders
                                    </Link>
                                </TableHead>
                                <TableHead
                                    class="text-right"
                                    :aria-sort="ariaSort('lifetime_spent_cents')"
                                >
                                    <Link
                                        :href="sortHref('lifetime_spent_cents')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Lifetime spend
                                    </Link>
                                </TableHead>
                                <TableHead>Last order</TableHead>
                                <TableHead :aria-sort="ariaSort('created_at')">
                                    <Link
                                        :href="sortHref('created_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Registered
                                    </Link>
                                </TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="customer in customers"
                                :key="customer.id"
                            >
                                <TableCell class="max-w-56 font-medium">
                                    <Link
                                        :href="adminCustomer(customer.id)"
                                        class="block truncate hover:underline"
                                    >
                                        {{ customer.name }}
                                    </Link>
                                </TableCell>
                                <TableCell class="max-w-64">
                                    <span
                                        class="text-muted-foreground block truncate"
                                    >
                                        {{ customer.email }}
                                    </span>
                                    <Badge
                                        v-if="!customer.emailVerifiedAt"
                                        variant="outline"
                                        class="mt-1"
                                    >
                                        Unverified
                                    </Badge>
                                </TableCell>
                                <TableCell class="tabular-nums">
                                    {{ customer.orderCount }}
                                </TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                >
                                    {{ customer.lifetimeSpentFormatted }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    <template v-if="customer.lastOrderAt">
                                        {{ formatIsoDate(customer.lastOrderAt) }}
                                    </template>
                                    <template v-else>—</template>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatIsoDate(customer.registeredAt) }}
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="adminCustomer(customer.id)">
                                            View
                                            <span class="sr-only">
                                                customer {{ customer.name }}
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
