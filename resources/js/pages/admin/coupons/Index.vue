<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, TicketPercent } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
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
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Coupons', href: adminCoupons().url },
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
        toUrl: (query) => adminCoupons.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        fields: {
            search: filters.search ?? '',
            type: filters.type ?? '',
            state: filters.state ?? '',
        },
    });

/** "3 of 100" when a limit is set, "3" when the code is unlimited. */
function usageLabel(coupon: App.Data.AdminCouponRowData): string {
    return coupon.usageLimit === null
        ? String(coupon.usedCount)
        : `${coupon.usedCount} of ${coupon.usageLimit}`;
}
</script>

<template>
    <div class="flex flex-col gap-6">
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

        <!--
          One card, three strips: filters, table, pagination. Not three cards —
          they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Code or note"
                search-label="Search coupons"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.type"
                    class="w-40"
                    label="Discount type"
                    all-label="All types"
                >
                    <SelectItem
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.state"
                    class="w-40"
                    label="State"
                    all-label="Any state"
                >
                    <SelectItem
                        v-for="option in stateOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="coupons.length === 0"
                :icon="TicketPercent"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching coupons' : 'No coupons yet'"
                :description="
                    isFiltered
                        ? 'No coupons match these filters.'
                        : 'A discount code you create appears here.'
                "
            >
                <template #action>
                    <Button size="sm" as-child>
                        <Link :href="adminCouponCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            New coupon
                        </Link>
                    </Button>
                </template>
            </AdminEmptyState>

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <AdminTable>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Code"
                                :href="sortHref('code')"
                                :sort="ariaSort('code')"
                            />
                            <TableHead>Discount</TableHead>
                            <TableHead>Minimum spend</TableHead>
                            <AdminSortableHead
                                label="Redeemed"
                                :href="sortHref('used_count')"
                                :sort="ariaSort('used_count')"
                            />
                            <AdminSortableHead
                                label="Expires"
                                :href="sortHref('expires_at')"
                                :sort="ariaSort('expires_at')"
                            />
                            <TableHead>State</TableHead>
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="coupon in coupons" :key="coupon.id">
                            <TableCell class="font-medium">
                                <Link
                                    :href="adminCoupon(coupon.id)"
                                    class="hover:text-primary transition-colors"
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
                                <AdminStatusBadge
                                    :label="
                                        coupon.isRedeemable
                                            ? 'Live'
                                            : 'Not live'
                                    "
                                    :tone="
                                        coupon.isRedeemable
                                            ? 'success'
                                            : 'neutral'
                                    "
                                />
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
                </AdminTable>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
