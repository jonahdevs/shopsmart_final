<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Boxes,
    ExternalLink,
    MoreHorizontal,
    Plus,
    SquarePen,
    Tags,
} from '@lucide/vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    create as adminBrandCreate,
    edit as adminBrandEdit,
    index as adminBrands,
} from '@/routes/admin/brands';
import { index as adminProducts } from '@/routes/admin/products';

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

/**
 * The host, so the column reads "rondo.co.ke" rather than wrapping a hundred
 * characters of tracking parameters.
 *
 * The stored value passed a `url` rule on the way in, so the parse should not
 * throw — but a row saved before that rule existed would take the whole table
 * down with it, and the full URL is a perfectly good label to fall back on.
 */
function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

/** The products table, narrowed to one brand — the id is what it filters on. */
function productsHref(brandId: number): string {
    return adminProducts.url({ query: { brand: String(brandId) } });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Brands" />

        <AdminPageHeader
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
                <AdminFilterSelect
                    v-model="form.active"
                    class="w-40"
                    label="Availability"
                    all-label="All brands"
                >
                    <SelectItem value="1">Active</SelectItem>
                    <SelectItem value="0">Inactive</SelectItem>
                </AdminFilterSelect>
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
                <AdminTable>
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
                            <!--
                              A link rather than printed text, which is what the
                              reference build does and what the column is for:
                              the one reason to look at a brand's website from
                              here is to open it. Only the host is shown — the
                              rest of a URL is noise in a table.
                            -->
                            <TableCell class="max-w-64">
                                <a
                                    v-if="brand.websiteUrl"
                                    :href="brand.websiteUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-muted-foreground hover:text-foreground inline-flex max-w-full items-center gap-1 transition-colors"
                                >
                                    <span class="truncate">
                                        {{ hostOf(brand.websiteUrl) }}
                                    </span>
                                    <ExternalLink
                                        class="size-3 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only">
                                        (opens in a new tab)
                                    </span>
                                </a>
                                <span v-else class="text-muted-foreground">
                                    —
                                </span>
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
                            <!--
                              A three-dots menu rather than a lone Edit button,
                              the same shape the products table uses. The
                              product count beside it is the number staff read
                              before deleting a brand, and until this menu there
                              was no way to see the products it counts.

                              There is no "View on store": brands have no
                              storefront page here, so the products table
                              filtered to the brand is the closest true thing.
                              Delete stays on the editor, where the sentence
                              about its products becoming unbranded sits beside
                              the button.
                            -->
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Actions for ${brand.name}`"
                                        >
                                            <MoreHorizontal
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>

                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52"
                                    >
                                        <DropdownMenuItem as-child>
                                            <Link
                                                class="block w-full"
                                                :href="
                                                    adminBrandEdit(brand.slug)
                                                "
                                            >
                                                <SquarePen
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Edit
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem as-child>
                                            <Link
                                                class="block w-full"
                                                :href="productsHref(brand.id)"
                                            >
                                                <Boxes
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                View products
                                                <span
                                                    class="text-muted-foreground ml-auto text-xs tabular-nums"
                                                >
                                                    {{ brand.productCount }}
                                                </span>
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
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
