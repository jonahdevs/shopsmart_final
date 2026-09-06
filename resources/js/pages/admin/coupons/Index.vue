<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
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
import { formatIsoDate } from '@/lib/utils';
import {
    create as adminCouponCreate,
    index as adminCoupons,
    show as adminCoupon,
} from '@/routes/admin/coupons';

type CouponFilters = {
    search: string | null;
    type: string | null;
    state: string | null;
    sort: string;
    direction: string;
};

const { coupons, pagination, filters, typeOptions, stateOptions } =
    defineProps<{
        coupons: App.Data.AdminCouponRowData[];
        pagination: App.Data.PaginationData;
        filters: CouponFilters;
        typeOptions: { value: string; label: string }[];
        stateOptions: { value: string; label: string }[];
    }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Coupons', href: '/admin/coupons' },
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
    type: filters.type ?? '',
    state: filters.state ?? '',
});

/**
 * Only the filters that are actually set. Sending empty strings would put
 * `?type=` on every URL and make two identical views look like different pages
 * to the browser's history.
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

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminCoupons.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminCoupons.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminCoupons.url({ query: activeQuery({ sort: column, direction }) });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}

/** "3 of 100" when a limit is set, "3" when the code is unlimited. */
function usageLabel(coupon: App.Data.AdminCouponRowData): string {
    return coupon.usageLimit === null
        ? String(coupon.usedCount)
        : `${coupon.usedCount} of ${coupon.usageLimit}`;
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Coupons" />

        <AdminPageHeader
            title="Coupons"
            :description="`${pagination.total} discount code${pagination.total === 1 ? '' : 's'}.`"
        >
            <template #actions>
                <Button size="sm" as-child>
                    <Link :href="adminCouponCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New coupon
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="coupon-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="coupon-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Code or note"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="coupon-type-filter">Type</Label>
                        <NativeSelect id="coupon-type-filter" v-model="form.type">
                            <option value="">All types</option>
                            <option
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="coupon-state">State</Label>
                        <NativeSelect id="coupon-state" v-model="form.state">
                            <option value="">Any state</option>
                            <option
                                v-for="option in stateOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="coupons.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No coupons match these filters.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :aria-sort="ariaSort('code')">
                                    <Link
                                        :href="sortHref('code')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Code
                                    </Link>
                                </TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>Minimum spend</TableHead>
                                <TableHead :aria-sort="ariaSort('used_count')">
                                    <Link
                                        :href="sortHref('used_count')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Redeemed
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('expires_at')">
                                    <Link
                                        :href="sortHref('expires_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Expires
                                    </Link>
                                </TableHead>
                                <TableHead>State</TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="coupon in coupons"
                                :key="coupon.id"
                            >
                                <TableCell class="font-medium">
                                    <Link
                                        :href="adminCoupon(coupon.id)"
                                        class="hover:underline"
                                    >
                                        {{ coupon.code }}
                                    </Link>
                                    <span
                                        v-if="coupon.description"
                                        class="text-muted-foreground block max-w-56 truncate text-xs"
                                    >
                                        {{ coupon.description }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ coupon.valueLabel }}
                                    <span
                                        v-if="coupon.maxDiscountFormatted"
                                        class="text-muted-foreground block text-xs"
                                    >
                                        max {{ coupon.maxDiscountFormatted }}
                                    </span>
                                </TableCell>
                                <TableCell class="tabular-nums">
                                    {{
                                        coupon.minSubtotalCents > 0
                                            ? coupon.minSubtotalFormatted
                                            : '—'
                                    }}
                                </TableCell>
                                <TableCell class="tabular-nums">
                                    {{ usageLabel(coupon) }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    <template v-if="coupon.expiresAt">
                                        {{ formatIsoDate(coupon.expiresAt) }}
                                    </template>
                                    <template v-else>Never</template>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            coupon.isRedeemable
                                                ? 'default'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            coupon.isRedeemable
                                                ? 'Live'
                                                : 'Not live'
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="adminCoupon(coupon.id)">
                                            View
                                            <span class="sr-only">
                                                coupon {{ coupon.code }}
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
