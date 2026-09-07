<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Tags } from '@lucide/vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminBrandCreate,
    edit as adminBrandEdit,
    index as adminBrands,
} from '@/routes/admin/brands';

type BrandFilters = {
    search: string | null;
    active: string | null;
    sort: string;
    direction: string;
};

const { brands, pagination, filters } = defineProps<{
    brands: App.Data.AdminBrandRowData[];
    pagination: App.Data.PaginationData;
    filters: BrandFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Brands', href: adminBrands().url },
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
        toUrl: (query) => adminBrands.url({ query }),
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
        <Head title="Brands" />

        <AdminPageHeader
            eyebrow="Catalog"
            title="Brands"
            :description="`${pagination.total} brand${pagination.total === 1 ? '' : 's'}.`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="adminBrandCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New brand
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
                search-label="Search brands"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <NativeSelect
                    v-model="form.active"
                    class="w-40"
                    aria-label="Availability"
                >
                    <option value="">All brands</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </NativeSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="brands.length === 0"
                :icon="Tags"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching brands' : 'No brands yet'"
                :description="
                    isFiltered
                        ? 'No brands match these filters.'
                        : 'Brands group products by who makes them.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="adminBrandCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create a brand
                        </Link>
                    </Button>
                </template>
            </AdminEmptyState>

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="Brand"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <TableHead>Website</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Products</TableHead>
                            <AdminSortableHead
                                label="Order"
                                align="end"
                                class="text-right"
                                :href="sortHref('sort_order')"
                                :sort="ariaSort('sort_order')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="brand in brands" :key="brand.id">
                            <TableCell class="font-medium">
                                {{ brand.name }}
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ brand.slug }}
                                </span>
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground max-w-64 truncate"
                            >
                                {{ brand.websiteUrl ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="
                                        brand.isActive ? 'Active' : 'Inactive'
                                    "
                                    :variant="
                                        brand.isActive ? 'default' : 'outline'
                                    "
                                />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ brand.productCount }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ brand.sortOrder }}
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="adminBrandEdit(brand.slug)">
                                        Edit
                                        <span class="sr-only">
                                            {{ brand.name }}
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
