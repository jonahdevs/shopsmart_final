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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Payments', href: '/admin/payments' },
        ],
    },
});

const form = ref({
    search: filters.search ?? '',
    status: filters.status ?? '',
    gateway: filters.gateway ?? '',
});

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

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminPayments.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminPayments.url({ query: activeQuery({ page }) });
}

function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminPayments.url({ query: activeQuery({ sort: column, direction }) });
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
        <Head title="Payments" />

        <AdminPageHeader
            title="Payments"
            description="Every attempt to collect, for reconciliation. Read-only."
        />

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="payment-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="payment-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Reference or order number"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="payment-status">Status</Label>
                        <NativeSelect id="payment-status" v-model="form.status">
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
                        <Label for="payment-gateway">Gateway</Label>
                        <NativeSelect
                            id="payment-gateway"
                            v-model="form.gateway"
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
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="payments.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No payments match these filters.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reference</TableHead>
                                <TableHead>Order</TableHead>
                                <TableHead>Gateway</TableHead>
                                <TableHead :aria-sort="ariaSort('status')">
                                    <Link
                                        :href="sortHref('status')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Status
                                    </Link>
                                </TableHead>
                                <TableHead
                                    class="text-right"
                                    :aria-sort="ariaSort('amount_cents')"
                                >
                                    <Link
                                        :href="sortHref('amount_cents')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Amount
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('created_at')">
                                    <Link
                                        :href="sortHref('created_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Attempted
                                    </Link>
                                </TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="payment in payments"
                                :key="payment.id"
                            >
                                <TableCell
                                    class="max-w-56 font-mono text-xs break-all"
                                >
                                    {{ payment.reference }}
                                </TableCell>
                                <TableCell>
                                    <Link
                                        v-if="payment.orderNumber"
                                        :href="adminOrder(payment.orderNumber)"
                                        class="hover:underline"
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
                                    <Badge
                                        :variant="
                                            toBadgeVariant(payment.statusVariant)
                                        "
                                    >
                                        {{ payment.statusLabel }}
                                    </Badge>
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
            </CardContent>
        </Card>

        <AdminPagination
            :pagination="pagination"
            :href-for-page="hrefForPage"
        />
    </div>
</template>
