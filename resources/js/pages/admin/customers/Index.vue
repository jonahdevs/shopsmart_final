<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Banknote, ShoppingBag, UserPlus, Users } from '@lucide/vue';
import { computed } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
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
    index as adminCustomers,
    show as adminCustomer,
} from '@/routes/admin/customers';

type CustomerFilters = {
    search: string | null;
    sort: string;
    direction: string;
};

const { customers, pagination, filters, stats } = defineProps<{
    customers: App.Data.AdminCustomerRowData[];
    pagination: App.Data.PaginationData;
    filters: CustomerFilters;
    stats: App.Data.AdminCustomerStatsData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Customers', href: adminCustomers().url },
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
        toUrl: (query) => adminCustomers.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        fields: {
            search: filters.search ?? '',
        },
    });

/*
  How many people there are, how many arrived lately, and how many of them have
  ever actually bought anything — the last is the one figure that separates a
  mailing list from a customer base.

  None of these tiles links anywhere: the table filters on free text alone, so
  a tile pretending to open "the 40 who have ordered" would land on a list that
  is not that. A count without a link is honest; a link to the wrong list is not.
*/
const tiles = computed(() => [
    {
        label: 'Customers',
        value: String(stats.customerCount),
        icon: Users,
        tone: 'brand' as const,
        hint: 'Registered, all time',
        change: null,
    },
    {
        label: 'New customers',
        value: String(stats.newCustomerCount),
        icon: UserPlus,
        tone: 'info' as const,
        hint: stats.periodLabel,
        change: stats.newCustomerChangePercent,
    },
    {
        label: 'Have ordered',
        value: String(stats.payingCustomerCount),
        icon: ShoppingBag,
        tone: 'success' as const,
        hint:
            stats.payingCustomerSharePercent === null
                ? undefined
                : `${stats.payingCustomerSharePercent}% of customers`,
        change: null,
    },
    {
        label: 'Average spend',
        value: stats.averageSpendFormatted,
        icon: Banknote,
        tone: 'accent' as const,
        hint: 'Per paying customer',
        change: null,
    },
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Customers" />

        <AdminPageHeader
            title="Customers"
            :description="`${pagination.total} registered customer${pagination.total === 1 ? '' : 's'}.`"
        />

        <!--
          The whole customer base, not the searched page below. These figures
          are the context a staff member searches against, so they hold still
          while the table moves.
        -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :icon="tile.icon"
                :tone="tile.tone"
                :hint="tile.hint"
                :change="tile.change"
            />
        </div>

        <!--
          One card, three strips: filters, table, pagination. Not three cards —
          they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Name or email"
                search-label="Search customers"
                :show-clear="isFiltered"
                @clear="clear"
            />

            <AdminEmptyState
                v-if="customers.length === 0"
                :icon="Users"
                :filtered="isFiltered"
                :title="
                    isFiltered ? 'No matching customers' : 'No customers yet'
                "
                :description="
                    isFiltered
                        ? 'No customers match this search.'
                        : 'Accounts registered on the storefront land here.'
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
                                label="Customer"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <AdminSortableHead
                                label="Email"
                                :href="sortHref('email')"
                                :sort="ariaSort('email')"
                            />
                            <AdminSortableHead
                                label="Orders"
                                :href="sortHref('orders_count')"
                                :sort="ariaSort('orders_count')"
                            />
                            <AdminSortableHead
                                label="Lifetime spend"
                                :href="sortHref('lifetime_spent_cents')"
                                :sort="ariaSort('lifetime_spent_cents')"
                            />
                            <TableHead>Last order</TableHead>
                            <AdminSortableHead
                                label="Registered"
                                :href="sortHref('created_at')"
                                :sort="ariaSort('created_at')"
                            />
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
                                    class="hover:text-primary block truncate transition-colors"
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
                                <AdminStatusBadge
                                    v-if="!customer.emailVerifiedAt"
                                    label="Unverified"
                                    tone="warning"
                                    class="mt-1"
                                />
                            </TableCell>
                            <TableCell class="tabular-nums">
                                {{ customer.orderCount }}
                            </TableCell>
                            <TableCell class="font-medium tabular-nums">
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
                </AdminTable>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
