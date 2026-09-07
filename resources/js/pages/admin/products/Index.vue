<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package, Plus } from '@lucide/vue';
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
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminProductCreate,
    edit as adminProductEdit,
    index as adminProducts,
} from '@/routes/admin/products';

type ProductFilters = {
    search: string | null;
    status: string | null;
    visibility: string | null;
    stock_status: string | null;
    category: number | string | null;
    brand: number | string | null;
    trashed: string | null;
    sort: string;
    direction: string;
};

type Option = { value: string; label: string };
type IdOption = { value: number; label: string };

/** U+00A0. A browser collapses ordinary leading whitespace inside an option. */
const NBSP = String.fromCharCode(160);

const {
    products,
    pagination,
    filters,
    statusOptions,
    visibilityOptions,
    stockStatusOptions,
    categoryOptions,
    brandOptions,
} = defineProps<{
    products: App.Data.AdminProductRowData[];
    pagination: App.Data.PaginationData;
    filters: ProductFilters;
    statusOptions: Option[];
    visibilityOptions: Option[];
    stockStatusOptions: Option[];
    categoryOptions: App.Data.AdminCategoryOptionData[];
    brandOptions: IdOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Products', href: adminProducts().url },
        ],
    },
});

const { can } = usePermissions();

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter. `useIndexTable` owns the debounce, the visit options and
 * the rule that empty filters are omitted rather than sent blank.
 *
 * The two id filters are carried as strings because a `<select>` value always
 * is — the controller casts them back.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminProducts.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        fields: {
            search: filters.search ?? '',
            status: filters.status ?? '',
            visibility: filters.visibility ?? '',
            stock_status: filters.stock_status ?? '',
            category: filters.category === null ? '' : String(filters.category),
            brand: filters.brand === null ? '' : String(filters.brand),
            trashed: filters.trashed ?? '',
        },
    });

/**
 * Indent an option so a flat select still reads as the category tree.
 *
 * Non-breaking spaces: a browser collapses ordinary leading whitespace inside
 * an option, which is exactly the whitespace carrying the depth.
 */
function indent(depth: number): string {
    return NBSP.repeat(depth * 2);
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Products" />

        <AdminPageHeader
            eyebrow="Catalog"
            title="Products"
            :description="`${pagination.total} product${pagination.total === 1 ? '' : 's'} in the catalog.`"
        >
            <template v-if="can('products.manage')" #actions>
                <Button as-child>
                    <Link :href="adminProductCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New product
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
                search-placeholder="Name, SKU or model number"
                search-label="Search products"
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
                    v-model="form.visibility"
                    class="w-40"
                    aria-label="Visibility"
                >
                    <option value="">Any visibility</option>
                    <option
                        v-for="option in visibilityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.category"
                    class="w-44"
                    aria-label="Category"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="option in categoryOptions"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ indent(option.depth) }}{{ option.name }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.brand"
                    class="w-40"
                    aria-label="Brand"
                >
                    <option value="">All brands</option>
                    <option
                        v-for="option in brandOptions"
                        :key="option.value"
                        :value="String(option.value)"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.stock_status"
                    class="w-40"
                    aria-label="Stock"
                >
                    <option value="">Any stock state</option>
                    <option
                        v-for="option in stockStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.trashed"
                    class="w-40"
                    aria-label="Bin"
                >
                    <option value="">Live products</option>
                    <option value="with">Include deleted</option>
                    <option value="only">Deleted only</option>
                </NativeSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="products.length === 0"
                :icon="Package"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching products' : 'No products yet'"
                :description="
                    isFiltered
                        ? 'No products match these filters.'
                        : 'The catalog is empty. Add the first product to sell it.'
                "
            >
                <template v-if="can('products.manage')" #action>
                    <Button as-child>
                        <Link :href="adminProductCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create a product
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
                                label="Product"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <AdminSortableHead
                                label="SKU"
                                :href="sortHref('sku')"
                                :sort="ariaSort('sku')"
                            />
                            <TableHead>Brand</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Stock</TableHead>
                            <AdminSortableHead
                                label="Price"
                                align="end"
                                class="text-right"
                                :href="sortHref('price')"
                                :sort="ariaSort('price')"
                            />
                            <AdminSortableHead
                                label="Updated"
                                :href="sortHref('updated_at')"
                                :sort="ariaSort('updated_at')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="product in products" :key="product.id">
                            <TableCell class="max-w-72 font-medium">
                                <span class="block truncate">
                                    {{ product.name }}
                                </span>
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ product.typeLabel }}
                                    <template v-if="product.variantCount">
                                        ·
                                        {{ product.variantCount }}
                                        variant<template
                                            v-if="product.variantCount !== 1"
                                            >s</template
                                        >
                                    </template>
                                </span>
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground tabular-nums"
                            >
                                {{ product.sku ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ product.brandName ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ product.categoryName ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap items-center gap-1">
                                    <AdminStatusBadge
                                        :label="product.statusLabel"
                                        :variant="product.statusVariant"
                                    />
                                    <AdminStatusBadge
                                        v-if="product.isDeleted"
                                        label="Deleted"
                                        variant="destructive"
                                    />
                                </div>
                                <span
                                    class="text-muted-foreground mt-1 block text-xs"
                                >
                                    {{ product.visibilityLabel }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="product.stockStatusLabel"
                                    :variant="product.stockStatusVariant"
                                />
                                <span
                                    v-if="product.stockQuantity !== null"
                                    class="text-muted-foreground mt-1 block text-xs tabular-nums"
                                >
                                    {{ product.stockQuantity }} on hand
                                </span>
                            </TableCell>
                            <TableCell
                                class="text-right font-medium tabular-nums"
                            >
                                {{ product.priceFormatted ?? 'On application' }}
                                <span
                                    v-if="product.isOnSale"
                                    class="text-muted-foreground block text-xs"
                                >
                                    on sale
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatIsoDate(product.updatedAt) }}
                            </TableCell>
                            <TableCell>
                                <Button
                                    v-if="
                                        can('products.manage') &&
                                        !product.isDeleted
                                    "
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="adminProductEdit(product.slug)"
                                    >
                                        Edit
                                        <span class="sr-only">
                                            {{ product.name }}
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
