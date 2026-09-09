<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    ExternalLink,
    ImageOff,
    MoreHorizontal,
    Package,
    PackageCheck,
    PackageX,
    PencilLine,
    Plus,
    SquarePen,
    Tag,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductBulkController from '@/actions/App/Http/Controllers/Admin/ProductBulkController';
import AdminBulkBar from '@/components/admin/AdminBulkBar.vue';
import AdminBulkResult from '@/components/admin/AdminBulkResult.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
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
import { usePermissions } from '@/composables/usePermissions';
import { useRowSelection } from '@/composables/useRowSelection';
import { formatIsoDate, toUrl } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminProductCreate,
    edit as adminProductEdit,
    index as adminProducts,
} from '@/routes/admin/products';
import { show as storefrontProduct } from '@/routes/product';

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

/** U+00A0. A browser collapses the ordinary leading whitespace in markup. */
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
    bulkStatusOptions,
    bulkResult,
    stats,
} = defineProps<{
    products: App.Data.AdminProductRowData[];
    pagination: App.Data.PaginationData;
    filters: ProductFilters;
    statusOptions: Option[];
    visibilityOptions: Option[];
    stockStatusOptions: Option[];
    categoryOptions: App.Data.AdminCategoryOptionData[];
    brandOptions: IdOption[];
    /** The bulk bar's status buttons, labelled as imperatives by the server. */
    bulkStatusOptions: Option[];
    /**
     * The outcome of the bulk action that redirected here, or null on any
     * other visit. Flashed for one request, so it cannot outlive its click.
     */
    bulkResult: App.Data.BulkActionResultData | null;
    stats: App.Data.AdminProductStatsData;
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
 * Reading the table and changing it are different permissions, so a Support
 * user gets no checkbox column at all rather than a column of controls whose
 * every action the server would refuse.
 */
const canManage = computed(() => can('products.manage'));

/**
 * Ticked rows, scoped to the page on screen and dropped on every visit.
 *
 * The scoping is the decision worth defending here — see `useRowSelection` for
 * the reasoning. Short version: `useIndexTable` visits with `preserveState`, so
 * a selection that is not explicitly reset survives filtering, and a staff
 * member can end up holding thirty-five ids while looking at ten rows.
 */
const {
    count: selectedCount,
    ids: selectedIds,
    isSelected,
    setSelected,
    allSelected,
    someSelected,
    setAllSelected,
    clear: clearSelection,
} = useRowSelection(() => products.map((product) => product.id));

/**
 * reka's checkbox takes `'indeterminate'` as a third state rather than a
 * separate prop, and clicking one emits `true` — so a partial page selects the
 * rest, which is what "some are ticked" invites.
 */
const selectAllState = computed<boolean | 'indeterminate'>(() =>
    someSelected.value ? 'indeterminate' : allSelected.value,
);

/**
 * The result strip is dismissible for the reader who has read it. The bulk bar
 * needs no reset: the server flashes `bulkResult` for exactly one request and
 * those forms post without `preserveState`, so the page remounts with this back
 * at false. A row menu action does preserve state, and resets it by hand — see
 * `runRowAction`.
 */
const resultDismissed = ref(false);

/**
 * True when the result on screen came from a row's Actions menu rather than the
 * bulk bar.
 *
 * Survives the redirect because `runRowAction` visits with `preserveState`,
 * which is the only reason a plain `ref` can qualify a prop that arrives one
 * request later.
 */
const resultIsFromRowMenu = ref(false);

/**
 * Whether the result strip belongs on screen.
 *
 * A bulk click always gets it: it is the only place the per-row reasons are
 * printed, and "18 updated, 4 skipped" is unreadable without them. A one-row
 * click gets it only when something was skipped or refused — "1 moved to the
 * bin." is a sentence the toast has already said, and a strip that appears
 * after every menu click is a strip staff stop reading before the one that
 * matters arrives. A one-row refusal is the opposite: it is the reason the
 * click did nothing, and it is the only place that reason is written down.
 */
const showsBulkResult = computed(
    () =>
        bulkResult !== null &&
        !resultDismissed.value &&
        !(resultIsFromRowMenu.value && bulkResult.isClean),
);

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter. `useIndexTable` owns the debounce, the visit options and
 * the rule that empty filters are omitted rather than sent blank.
 *
 * The two id filters are carried as strings because that is what the select's
 * item values are — the controller casts them back.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminProducts.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        // The one visit that preserves state, so the one that would otherwise
        // carry a selection across a change of what is being selected from.
        onBeforeVisit: clearSelection,
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

/*
  The three real filter values are typed against the generated enums, so a
  status renamed on the server fails the build here rather than silently opening
  an empty list. `low_stock` cannot be: it is not a column value but the
  threshold comparison `ProductIndexRequest::LOW_STOCK` names, which is also why
  the server sends the threshold itself rather than leaving the tile to guess.
*/
const LIVE: App.Enums.ProductStatus = 'published';
const UNFINISHED: App.Enums.ProductStatus = 'draft';
const OUT_OF_STOCK: App.Enums.StockStatus = 'out_of_stock';
const LOW_STOCK = 'low_stock';

/*
  Four counts, and every one of them is a filter of the very table below it, so
  every one carries the filter that produces it — a tile reading "Out of stock:
  12" that a manager cannot click through to the twelve products has told them a
  number and denied them the work.

  No deltas here, unlike the orders and overview rows. A catalog has no trading
  window: "live products" is the state of the shelf right now, not a result for
  the month, and a percentage against last month would be arithmetic dressed up
  as insight.

  The stock pair overlaps — a product sitting on zero is in both — and that is
  correct, because these are four separate questions rather than four slices of
  one total. The low-stock tile names its threshold instead of saying "view
  list", since the number is meaningless without it.
*/
const tiles = computed(() => [
    {
        label: 'Live products',
        value: String(stats.publishedCount),
        icon: PackageCheck,
        tone: 'success' as const,
        href: adminProducts.url({ query: { status: LIVE } }),
        hint: 'View list',
    },
    {
        label: 'Drafts',
        value: String(stats.draftCount),
        icon: PencilLine,
        tone: 'neutral' as const,
        href: adminProducts.url({ query: { status: UNFINISHED } }),
        hint: 'View list',
    },
    {
        label: 'Low stock',
        value: String(stats.lowStockCount),
        icon: TriangleAlert,
        tone: 'warning' as const,
        href: adminProducts.url({ query: { stock_status: LOW_STOCK } }),
        hint: `At or below ${stats.lowStockThreshold}`,
    },
    {
        label: 'Out of stock',
        value: String(stats.outOfStockCount),
        icon: PackageX,
        tone: 'danger' as const,
        href: adminProducts.url({ query: { stock_status: OUT_OF_STOCK } }),
        hint: 'View list',
    },
]);

/**
 * The bin filter is the only view where restoring makes sense as a bulk action.
 *
 * `trashed=with` mixes live and deleted rows, so a Restore button there would
 * be offered against a selection that is mostly ineligible — the server refuses
 * those ids outright, which is the right answer to a request that should never
 * have been made. Narrowing to the bin first is one click and makes the button
 * mean what it says. Until this slice, `trashed=only` had no restore path at
 * all and was a filter staff could reach and not act from.
 */
const showsBinOnly = computed(() => filters.trashed === 'only');

/**
 * Run one row's Actions-menu item through the bulk endpoint.
 *
 * A single row is a list of one, deliberately. `ProductBulkController` already
 * re-reads the row, recomputes eligibility, writes the activity entry and
 * answers with the applied/skipped/refused shape; a second per-row route would
 * be a second copy of all four rules, and the copies drift — the reference build
 * has exactly that, a `quickSetStatus` beside a bulk one. The server cannot tell
 * a menu click from a bulk click, and should not need to.
 *
 * `preserveState` is what lets `resultIsFromRowMenu` outlive the redirect: the
 * Vue adapter re-keys the page on any visit that does not preserve state, and a
 * flag that resets before the result it qualifies arrives is no flag at all.
 * Keeping the page mounted then makes the dismissal below the page's job rather
 * than the remount's.
 *
 * `status` is typed as the plain string the server sent in `bulkStatusOptions`
 * rather than the enum: that list is the closed set the endpoint validates
 * against, so narrowing it here would only restate a rule that already holds.
 */
function runRowAction(
    productId: number,
    action: 'status' | 'delete' | 'restore',
    status?: string,
): void {
    resultIsFromRowMenu.value = true;
    resultDismissed.value = false;

    router.post(
        ProductBulkController.url(),
        status === undefined
            ? { action, ids: [productId] }
            : { action, ids: [productId], status },
        { preserveScroll: true, preserveState: true },
    );
}

/**
 * Indent a row so a flat select still reads as the category tree.
 *
 * Non-breaking spaces: a browser collapses the ordinary leading whitespace in
 * markup, which is exactly the whitespace carrying the depth.
 */
function indent(depth: number): string {
    return NBSP.repeat(depth * 2);
}
</script>

<template>
    <div class="flex flex-col gap-6">
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

        <!--
          The whole catalog, not the filtered page below. These figures are the
          context a staff member filters against, so they hold still while the
          table moves.
        -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :icon="tile.icon"
                :tone="tile.tone"
                :href="tile.href"
                :hint="tile.hint"
            />
        </div>

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
                <AdminFilterSelect
                    v-model="form.status"
                    class="w-40"
                    label="Status"
                    all-label="All statuses"
                >
                    <SelectItem
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.visibility"
                    class="w-40"
                    label="Visibility"
                    all-label="Any visibility"
                >
                    <SelectItem
                        v-for="option in visibilityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.category"
                    class="w-44"
                    label="Category"
                    all-label="All categories"
                >
                    <SelectItem
                        v-for="option in categoryOptions"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ indent(option.depth) }}{{ option.name }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.brand"
                    class="w-40"
                    label="Brand"
                    all-label="All brands"
                >
                    <SelectItem
                        v-for="option in brandOptions"
                        :key="option.value"
                        :value="String(option.value)"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.stock_status"
                    class="w-40"
                    label="Stock"
                    all-label="Any stock state"
                >
                    <SelectItem
                        v-for="option in stockStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.trashed"
                    class="w-40"
                    label="Bin"
                    all-label="Live products"
                >
                    <SelectItem value="with">Include deleted</SelectItem>
                    <SelectItem value="only">Deleted only</SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <!--
              Sits above the bulk bar because it describes the click that just
              happened, and the bulk bar describes what is selected now.
            -->
            <AdminBulkResult
                v-if="showsBulkResult"
                :result="bulkResult!"
                @dismiss="resultDismissed = true"
            />

            <!--
              Every action is a real form post carrying the ticked ids, not a
              client-side loop over per-row requests: twenty-five separate
              PATCHes would half-succeed in a way nobody could report on, and
              the server has to recompute eligibility per id anyway.
            -->
            <AdminBulkBar
                v-if="canManage"
                :count="selectedCount"
                item-label="product"
                item-label-plural="products"
                @clear="clearSelection"
            >
                <Form
                    v-for="option in bulkStatusOptions"
                    :key="option.value"
                    v-bind="ProductBulkController.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="action" value="status" />
                    <input type="hidden" name="status" :value="option.value" />
                    <input
                        v-for="id in selectedIds"
                        :key="id"
                        type="hidden"
                        name="ids[]"
                        :value="id"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        :disabled="processing"
                    >
                        {{ option.label }}
                    </Button>
                </Form>

                <!--
                  No confirmation dialog, deliberately. The bin is a soft
                  delete and Restore is now one filter and one click away —
                  which is precisely what this slice added, and what makes an
                  interstitial here cost more than the mistake it prevents.
                -->
                <Form
                    v-if="!showsBinOnly"
                    v-bind="ProductBulkController.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="action" value="delete" />
                    <input
                        v-for="id in selectedIds"
                        :key="id"
                        type="hidden"
                        name="ids[]"
                        :value="id"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Move to bin
                    </Button>
                </Form>

                <Form
                    v-if="showsBinOnly"
                    v-bind="ProductBulkController.form()"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <input type="hidden" name="action" value="restore" />
                    <input
                        v-for="id in selectedIds"
                        :key="id"
                        type="hidden"
                        name="ids[]"
                        :value="id"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        :disabled="processing"
                    >
                        <ArchiveRestore class="size-4" aria-hidden="true" />
                        Restore
                    </Button>
                </Form>
            </AdminBulkBar>

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
                <AdminTable>
                    <TableHeader>
                        <TableRow>
                            <TableHead v-if="canManage" class="w-0">
                                <Checkbox
                                    :model-value="selectAllState"
                                    aria-label="Select every product on this page"
                                    @update:model-value="
                                        (value) =>
                                            setAllSelected(value === true)
                                    "
                                />
                            </TableHead>
                            <TableHead class="w-14">
                                <span class="sr-only">Image</span>
                            </TableHead>
                            <AdminSortableHead
                                label="Product"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <TableHead>Brand</TableHead>
                            <TableHead>Category</TableHead>
                            <AdminSortableHead
                                label="Price"
                                :href="sortHref('price')"
                                :sort="ariaSort('price')"
                            />
                            <TableHead>Stock</TableHead>
                            <TableHead>Status</TableHead>
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
                            <!--
                              The label names the product rather than saying
                              "Select row": twenty-five boxes all announced as
                              "select" are twenty-five identical controls to
                              anyone not reading the screen.
                            -->
                            <TableCell v-if="canManage">
                                <Checkbox
                                    :model-value="isSelected(product.id)"
                                    :aria-label="`Select ${product.name}`"
                                    @update:model-value="
                                        (value) =>
                                            setSelected(
                                                product.id,
                                                value === true,
                                            )
                                    "
                                />
                            </TableCell>
                            <!--
                              A picture is how a catalog manager recognises a
                              product; the name is how they confirm it. The box
                              is drawn whether or not there is a file, so the
                              column keeps its width and the rows stay level.
                            -->
                            <TableCell>
                                <div
                                    class="border-border bg-muted/40 flex size-10 items-center justify-center overflow-hidden rounded-md border"
                                >
                                    <img
                                        v-if="product.thumbUrl"
                                        :src="product.thumbUrl"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="size-full object-contain"
                                    />
                                    <ImageOff
                                        v-else
                                        class="text-muted-foreground/40 size-4"
                                        aria-hidden="true"
                                    />
                                </div>
                            </TableCell>
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
                                <span
                                    class="text-muted-foreground block font-mono text-xs"
                                >
                                    {{ product.sku ?? '—' }}
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ product.brandName ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ product.categoryName ?? '—' }}
                            </TableCell>
                            <TableCell class="font-medium tabular-nums">
                                {{ product.priceFormatted ?? 'On application' }}
                                <span
                                    v-if="product.isOnSale"
                                    class="text-muted-foreground block text-xs"
                                >
                                    on sale
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
                            <TableCell class="text-muted-foreground">
                                {{ formatIsoDate(product.updatedAt) }}
                            </TableCell>
                            <!--
                              One trigger per row, always drawn: everybody who
                              can reach this table holds `products.view`, which
                              is also the permission that opens a storefront
                              preview, so no row is ever an empty menu.

                              No `usePortalTheme()` here. The content teleports
                              to `document.body`, but the staff tokens are the
                              bare `:root` block rather than a scoped class, so
                              a portalled menu is already in the right palette —
                              that is the whole reason the tokens sit there.
                            -->
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <!--
                                          Named per row, not "Actions".
                                          Twenty-five buttons all announced as
                                          "Actions" are twenty-five identical
                                          controls to anyone not reading the
                                          screen — the same reason the row
                                          checkbox above names its product.
                                        -->
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Actions for ${product.name}`"
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
                                        <DropdownMenuItem
                                            v-if="canManage && !product.isDeleted"
                                            as-child
                                        >
                                            <Link
                                                class="block w-full"
                                                :href="
                                                    adminProductEdit(
                                                        product.slug,
                                                    )
                                                "
                                            >
                                                <SquarePen
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Edit
                                            </Link>
                                        </DropdownMenuItem>

                                        <!--
                                          Always a working link, because the
                                          storefront now renders a product that
                                          is not public as a staff preview —
                                          which is the whole point of the item:
                                          seeing how a draft will look before
                                          anyone else can. The wording changes
                                          rather than the availability, so
                                          nobody opens a tab expecting the live
                                          page and gets a banner instead.

                                          A binned product is the one exception.
                                          Route model binding resolves against
                                          the default scope, so the storefront
                                          404s on it for staff too — withdrawn
                                          is not the same as "not public yet".
                                        -->
                                        <DropdownMenuItem
                                            v-if="!product.isDeleted"
                                            as-child
                                        >
                                            <a
                                                class="block w-full"
                                                :href="
                                                    toUrl(
                                                        storefrontProduct(
                                                            product.slug,
                                                        ),
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                {{
                                                    product.isViewableOnStore
                                                        ? 'View on store'
                                                        : 'Preview on store'
                                                }}
                                                <span class="sr-only">
                                                    (opens in a new tab)
                                                </span>
                                                <span
                                                    v-if="
                                                        !product.isViewableOnStore
                                                    "
                                                    class="text-muted-foreground ml-auto text-xs"
                                                >
                                                    Staff only
                                                </span>
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-else disabled>
                                            <ExternalLink
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            View on store
                                            <span
                                                class="text-muted-foreground ml-auto text-xs"
                                            >
                                                In the bin
                                            </span>
                                        </DropdownMenuItem>

                                        <!--
                                          The same three statuses the bulk bar
                                          offers, from the same server list, so
                                          the menu cannot ask for something the
                                          endpoint would reject. The state the
                                          product already holds is disabled
                                          rather than removed: the submenu then
                                          reads the same on every row, and the
                                          greyed line says which state that is
                                          without the reader leaving the menu.
                                        -->
                                        <DropdownMenuSub
                                            v-if="
                                                canManage && !product.isDeleted
                                            "
                                        >
                                            <DropdownMenuSubTrigger>
                                                <Tag
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Set status
                                            </DropdownMenuSubTrigger>
                                            <DropdownMenuSubContent>
                                                <DropdownMenuItem
                                                    v-for="option in bulkStatusOptions"
                                                    :key="option.value"
                                                    :disabled="
                                                        option.value ===
                                                        product.status
                                                    "
                                                    @select="
                                                        runRowAction(
                                                            product.id,
                                                            'status',
                                                            option.value,
                                                        )
                                                    "
                                                >
                                                    {{ option.label }}
                                                    <span
                                                        v-if="
                                                            option.value ===
                                                            product.status
                                                        "
                                                        class="text-muted-foreground ml-auto text-xs"
                                                    >
                                                        Current
                                                    </span>
                                                </DropdownMenuItem>
                                            </DropdownMenuSubContent>
                                        </DropdownMenuSub>

                                        <!--
                                          Below a separator because these two
                                          take the row out of the catalog or put
                                          it back, which is a different kind of
                                          click from the ones above. Still no
                                          confirmation dialog: the bin is a soft
                                          delete and Restore is the very next
                                          item this menu grows on a binned row.
                                        -->
                                        <template v-if="canManage">
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-if="!product.isDeleted"
                                                variant="destructive"
                                                @select="
                                                    runRowAction(
                                                        product.id,
                                                        'delete',
                                                    )
                                                "
                                            >
                                                <Trash2
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Move to bin
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-else
                                                @select="
                                                    runRowAction(
                                                        product.id,
                                                        'restore',
                                                    )
                                                "
                                            >
                                                <ArchiveRestore
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Restore
                                            </DropdownMenuItem>
                                        </template>
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
