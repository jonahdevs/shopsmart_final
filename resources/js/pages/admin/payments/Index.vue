<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
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
import { show as adminOrder } from '@/routes/admin/orders';
import {
    index as adminPayments,
    show as adminPayment,
} from '@/routes/admin/payments';

type PaymentFilters = {
    search: string | null;
    status: string | null;
    gateway: string | null;
    sort: string;
    direction: string;
};

const { payments, pagination, filters, statusOptions, gateways } = defineProps<{
    payments: App.Data.AdminPaymentRowData[];
    pagination: App.Data.PaginationData;
    filters: PaymentFilters;
    statusOptions: { value: string; label: string }[];
    gateways: string[];
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
 * Reconciliation is done by narrowing, then sharing what you found: the filter
 * state lives in the URL so a staff member can paste "every failed Paystack
 * attempt" into a message. `useIndexTable` owns the debounce, the visit options
 * and the rule that empty filters are omitted rather than sent blank.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminPayments.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        fields: {
            search: filters.search ?? '',
            status: filters.status ?? '',
            gateway: filters.gateway ?? '',
        },
    });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Payments" />

        <AdminPageHeader
            eyebrow="Sales"
            title="Payments"
            description="Every attempt to collect, for reconciliation. Read-only."
        />

        <!--
          One card, three strips: filters, table, pagination. Not three cards —
          they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Reference or order number"
                search-label="Search payments"
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

                <!-- Only gateways that have actually been used are offered. -->
                <NativeSelect
                    v-model="form.gateway"
                    class="w-40"
                    aria-label="Gateway"
                >
                    <option value="">All gateways</option>
                    <option
                        v-for="gateway in gateways"
                        :key="gateway"
                        :value="gateway"
                    >
                        {{ gateway }}
                    </option>
                </NativeSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="payments.length === 0"
                :icon="CreditCard"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching payments' : 'No payments yet'"
                :description="
                    isFiltered
                        ? 'No payments match these filters.'
                        : 'Every collection attempt a gateway reports lands here.'
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
                            <TableHead>Reference</TableHead>
                            <TableHead>Order</TableHead>
                            <TableHead>Gateway</TableHead>
                            <AdminSortableHead
                                label="Status"
                                :href="sortHref('status')"
                                :sort="ariaSort('status')"
                            />
                            <AdminSortableHead
                                label="Amount"
                                align="end"
                                class="text-right"
                                :href="sortHref('amount_cents')"
                                :sort="ariaSort('amount_cents')"
                            />
                            <AdminSortableHead
                                label="Attempted"
                                :href="sortHref('created_at')"
                                :sort="ariaSort('created_at')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="payment in payments" :key="payment.id">
                            <TableCell
                                class="max-w-56 font-mono text-xs break-all"
                            >
                                {{ payment.reference }}
                            </TableCell>
                            <TableCell>
                                <!--
                                  An attempt can outlive the order it was made
                                  against, so the link is conditional.
                                -->
                                <Link
                                    v-if="payment.orderNumber"
                                    :href="adminOrder(payment.orderNumber)"
                                    class="hover:text-primary transition-colors"
                                >
                                    {{ payment.orderNumber }}
                                </Link>
                                <span v-else class="text-muted-foreground">
                                    —
                                </span>
                            </TableCell>
                            <TableCell>
                                {{ payment.gateway }}
                                <span
                                    v-if="payment.channel"
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ payment.channel }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="payment.statusLabel"
                                    :variant="payment.statusVariant"
                                />
                            </TableCell>
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
                                {{ payment.amountFormatted }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatIsoDate(payment.createdAt) }}
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="adminPayment(payment.id)">
                                        View
                                        <span class="sr-only">
                                            payment {{ payment.reference }}
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
