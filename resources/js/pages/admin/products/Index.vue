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
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Products', href: '/admin/products' },
        ],
    },
});

const { can } = usePermissions();

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter.
 */
const form = ref({
    search: filters.search ?? '',
    status: filters.status ?? '',
    visibility: filters.visibility ?? '',
    stock_status: filters.stock_status ?? '',
    category: filters.category === null ? '' : String(filters.category),
    brand: filters.brand === null ? '' : String(filters.brand),
    trashed: filters.trashed ?? '',
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

    if (filters.sort !== 'created_at' || filters.direction !== 'desc') {
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
            router.get(adminProducts.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminProducts.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminProducts.url({ query: activeQuery({ sort: column, direction }) });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}

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
    <div class="flex flex-col gap-6 p-4">
        <Head title="Products" />

        <AdminPageHeader
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

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="product-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="product-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Name, SKU or model number"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="product-status">Status</Label>
                        <NativeSelect id="product-status" v-model="form.status">
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
                        <Label for="product-visibility">Visibility</Label>
                        <NativeSelect
                            id="product-visibility"
                            v-model="form.visibility"
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
                    </div>

                    <div class="space-y-1.5">
                        <Label for="product-category">Category</Label>
                        <NativeSelect
                            id="product-category"
                            v-model="form.category"
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
                    </div>

                    <div class="space-y-1.5">
                        <Label for="product-brand">Brand</Label>
                        <NativeSelect id="product-brand" v-model="form.brand">
                            <option value="">All brands</option>
                            <option
                                v-for="option in brandOptions"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="product-stock">Stock</Label>
                        <NativeSelect
                            id="product-stock"
                            v-model="form.stock_status"
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
                    </div>

                    <div class="space-y-1.5">
                        <Label for="product-trashed">Bin</Label>
                        <NativeSelect
                            id="product-trashed"
                            v-model="form.trashed"
                        >
                            <option value="">Live products</option>
                            <option value="with">Include deleted</option>
                            <option value="only">Deleted only</option>
                        </NativeSelect>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="products.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No products match these filters.
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
                                        Product
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('sku')">
                                    <Link
                                        :href="sortHref('sku')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        SKU
                                    </Link>
                                </TableHead>
                                <TableHead>Brand</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Stock</TableHead>
                                <TableHead
                                    class="text-right"
                                    :aria-sort="ariaSort('price')"
                                >
                                    <Link
                                        :href="sortHref('price')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Price
                                    </Link>
                                </TableHead>
                                <TableHead :aria-sort="ariaSort('updated_at')">
                                    <Link
                                        :href="sortHref('updated_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Updated
                                    </Link>
                                </TableHead>
                                <TableHead class="w-0">
                                    <span class="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="product in products"
                                :key="product.id"
                            >
                                <TableCell class="max-w-72 font-medium">
                                    <span class="block truncate">
                                        {{ product.name }}
                                    </span>
                                    <span
                                        class="text-muted-foreground block text-xs"
                                    >
                                        {{ product.typeLabel }}
                                        <template v-if="product.variantCount">
                                            · {{ product.variantCount }} variant<template
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
                                        <Badge
                                            :variant="toBadgeVariant(product.statusVariant)"
                                        >
                                            {{ product.statusLabel }}
                                        </Badge>
                                        <Badge
                                            v-if="product.isDeleted"
                                            variant="destructive"
                                        >
                                            Deleted
                                        </Badge>
                                    </div>
                                    <span
                                        class="text-muted-foreground mt-1 block text-xs"
                                    >
                                        {{ product.visibilityLabel }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="toBadgeVariant(product.stockStatusVariant)"
                                    >
                                        {{ product.stockStatusLabel }}
                                    </Badge>
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
                                        v-if="can('products.manage') && !product.isDeleted"
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="adminProductEdit(product.slug)">
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
            </CardContent>
        </Card>

        <AdminPagination :pagination="pagination" :href-for-page="hrefForPage" />
    </div>
</template>
