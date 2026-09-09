<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Receipt } from '@lucide/vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminTaxClassCreate,
    edit as adminTaxClassEdit,
    index as adminTaxClasses,
} from '@/routes/admin/tax-classes';

type TaxClassFilters = {
    search: string | null;
    active: string | null;
    sort: string;
    direction: string;
};

const { taxClasses, pagination, filters } = defineProps<{
    taxClasses: App.Data.AdminTaxClassRowData[];
    pagination: App.Data.PaginationData;
    filters: TaxClassFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Tax classes', href: adminTaxClasses().url },
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
        toUrl: (query) => adminTaxClasses.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'name', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
            active: filters.active ?? '',
        },
    });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Tax classes" />

        <AdminPageHeader
            title="Tax classes"
            :description="`${pagination.total} VAT band${pagination.total === 1 ? '' : 's'}.`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="adminTaxClassCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New tax class
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
                search-placeholder="Name or slug"
                search-label="Search tax classes"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.active"
                    class="w-44"
                    label="Availability"
                    all-label="All tax classes"
                >
                    <SelectItem value="1">Active</SelectItem>
                    <SelectItem value="0">Inactive</SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="taxClasses.length === 0"
                :icon="Receipt"
                :filtered="isFiltered"
                :title="
                    isFiltered
                        ? 'No matching tax classes'
                        : 'No tax classes yet'
                "
                :description="
                    isFiltered
                        ? 'No tax classes match these filters.'
                        : 'A tax class is the VAT rate a product is charged at.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="adminTaxClassCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create a tax class
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
                                label="Tax class"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <AdminSortableHead
                                label="Rate"
                                align="end"
                                class="text-right"
                                :href="sortHref('rate')"
                                :sort="ariaSort('rate')"
                            />
                            <TableHead>Description</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Products</TableHead>
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="taxClass in taxClasses"
                            :key="taxClass.id"
                        >
                            <TableCell class="font-medium">
                                {{ taxClass.name }}
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ taxClass.slug }}
                                </span>
                            </TableCell>
                            <!--
                              A percentage, not money: printed as the server
                              cast it, with no currency formatting anywhere near
                              it.
                            -->
                            <TableCell class="text-right tabular-nums">
                                {{ taxClass.rate }}%
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground max-w-72 truncate"
                            >
                                {{ taxClass.description ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-1">
                                    <AdminStatusBadge
                                        :label="
                                            taxClass.isActive
                                                ? 'Active'
                                                : 'Inactive'
                                        "
                                        :variant="
                                            taxClass.isActive
                                                ? 'default'
                                                : 'outline'
                                        "
                                    />
                                    <!--
                                      Worth a badge of its own: the default band
                                      is the one charged to every product that
                                      names none, and it is the one delete
                                      refuses.
                                    -->
                                    <AdminStatusBadge
                                        v-if="taxClass.isStoreDefault"
                                        label="Store default"
                                        tone="info"
                                    />
                                </div>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ taxClass.productCount }}
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="adminTaxClassEdit(taxClass.id)"
                                    >
                                        Edit
                                        <span class="sr-only">
                                            {{ taxClass.name }}
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
