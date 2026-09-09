<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ExternalLink,
    ImageOff,
    MoreHorizontal,
    Plus,
    Search,
    SquarePen,
    Tags,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import TagProductController from '@/actions/App/Http/Controllers/Admin/TagProductController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { usePermissions } from '@/composables/usePermissions';
import { toUrl } from '@/lib/utils';
import { catalog } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as adminProductEdit } from '@/routes/admin/products';
import { index as adminTags } from '@/routes/admin/tags';
import { index as adminTagProducts } from '@/routes/admin/tags/products';
import { show as storefrontProduct } from '@/routes/product';

type TagProductFilters = {
    search: string | null;
    add: string | null;
    sort: string;
    direction: string;
};

const { tag, products, pagination, filters, candidates } = defineProps<{
    tag: App.Data.AdminTagRowData;
    products: App.Data.AdminProductRowData[];
    pagination: App.Data.PaginationData;
    candidates: App.Data.AdminTagProductOptionData[];
    filters: TagProductFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Tags', href: adminTags().url },
        ],
    },
});

const { can } = usePermissions();

/**
 * Both search boxes on this screen go through one `useIndexTable`.
 *
 * They are two questions about the same tag — what is on it, and what could
 * be — so they belong in one query string and one debounced visit. The
 * composable's visit preserves state, which is what keeps the add box's cursor
 * where the staff member left it while the results underneath change.
 */
const { form, hrefForPage, sortHref, ariaSort, clear } = useIndexTable({
    toUrl: (query) => adminTagProducts.url(tag.id, { query }),
    sortState: () => filters,
    defaultSort: { column: 'name', direction: 'asc' },
    fields: {
        search: filters.search ?? '',
        add: filters.add ?? '',
    },
});

/**
 * The table's own empty state asks only about the table's own filter.
 *
 * `isFiltered` would be true while somebody is typing in the add box, and
 * "no products match these filters" is then a lie about a list nothing is
 * filtering.
 */
const isSearchingTagged = computed(() => form.value.search !== '');

const isAdding = computed(() => form.value.add !== '');

const shopHref = computed(() =>
    toUrl(catalog.url({ query: { tag: tag.name } })),
);

/**
 * Take one product off the tag.
 *
 * `preserveScroll` because the row being removed is usually mid-table and the
 * reader is looking at it; the page reloads to the same place with one fewer
 * row rather than jumping to the top.
 */
function removeFromTag(slug: string): void {
    router.delete(
        TagProductController.destroy.url({ tag: tag.id, product: slug }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="`${tag.name} · Tagged products`" />

        <AdminPageHeader
            :title="tag.name"
            :description="`${pagination.total} product${pagination.total === 1 ? '' : 's'} carry this tag. This is the list any storefront rail built on it shows.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="adminTags()">All tags</Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="shopHref" target="_blank" rel="noopener">
                        <ExternalLink class="size-4" aria-hidden="true" />
                        View in shop
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </Button>
            </template>
        </AdminPageHeader>

        <!--
          A membership screen, not an index: the list on the left is the record
          and the picker on the right is how it is changed, so they are two
          cards side by side rather than strips of one.
        -->
        <div class="grid gap-6 lg:grid-cols-3">
            <AdminCard class="lg:col-span-2">
                <AdminFilterBar
                    v-model:search="form.search"
                    search-placeholder="Name or SKU"
                    search-label="Search tagged products"
                    :show-clear="isSearchingTagged"
                    @clear="clear"
                />

                <AdminEmptyState
                    v-if="products.length === 0"
                    :icon="Tags"
                    :filtered="isSearchingTagged"
                    :title="
                        isSearchingTagged
                            ? 'No matching products'
                            : 'Nothing carries this tag yet'
                    "
                    :description="
                        isSearchingTagged
                            ? 'No tagged product matches this search.'
                            : 'Find a product in the panel beside this one to put it on the tag.'
                    "
                />

                <!--
                  Wide content scrolls inside its own container so the page body
                  never scrolls sideways on a narrow screen.
                -->
                <div v-else class="overflow-x-auto">
                    <AdminTable>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-14">
                                    <span class="sr-only">Image</span>
                                </TableHead>
                                <AdminSortableHead
                                    label="Product"
                                    :href="sortHref('name')"
                                    :sort="ariaSort('name')"
                                />
                                <TableHead>Status</TableHead>
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
                                        class="text-muted-foreground block font-mono text-xs"
                                    >
                                        {{ product.sku ?? '—' }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <AdminStatusBadge
                                        :label="product.statusLabel"
                                        :variant="product.statusVariant"
                                    />
                                    <!--
                                      A tag on a draft product puts nothing on
                                      the shop floor, and a merchandiser
                                      wondering why a rail is short needs to see
                                      that here rather than deduce it.
                                    -->
                                    <span
                                        v-if="!product.isViewableOnStore"
                                        class="text-muted-foreground mt-1 block text-xs"
                                    >
                                        Not on the storefront
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
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
                                                v-if="can('products.manage')"
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
                                                    Edit product
                                                </Link>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem as-child>
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
                                                </a>
                                            </DropdownMenuItem>

                                            <!--
                                              Below a separator because it
                                              changes the list rather than
                                              navigating away from it. No
                                              confirmation: it removes a label,
                                              the product is untouched, and
                                              putting it back is one search in
                                              the panel alongside.
                                            -->
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                @select="
                                                    removeFromTag(product.slug)
                                                "
                                            >
                                                <X
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Remove from tag
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

            <!--
              A panel rather than a dialog. Adding several products in a row is
              the ordinary case, and a modal that has to be reopened between
              each one turns a two-minute job into forty clicks.
            -->
            <AdminCard class="h-fit">
                <AdminCardHeader title="Add a product" :icon="Plus" />

                <div class="space-y-3 p-5">
                    <div class="space-y-1.5">
                        <Label for="add">Search the catalog</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="add"
                                v-model="form.add"
                                type="search"
                                class="pl-8"
                                placeholder="Name or SKU"
                            />
                        </div>
                    </div>

                    <!--
                      Nothing is offered until something is typed: a list of the
                      first ten products alphabetically is not a picker, it is
                      an invitation to click whichever one happened to be there.
                    -->
                    <p
                        v-if="!isAdding"
                        class="text-muted-foreground text-sm"
                    >
                        Type a name or SKU to find products that are not on this
                        tag yet.
                    </p>

                    <p
                        v-else-if="candidates.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No product matches that, or every match already carries
                        the tag.
                    </p>

                    <ul v-else class="divide-y rounded-md border">
                        <li
                            v-for="candidate in candidates"
                            :key="candidate.id"
                        >
                            <Form
                                v-bind="
                                    TagProductController.store.form(tag.id)
                                "
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="product_id"
                                    :value="candidate.id"
                                />
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="hover:bg-muted/60 flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm disabled:opacity-60"
                                >
                                    <span class="min-w-0 truncate font-medium">
                                        {{ candidate.name }}
                                    </span>
                                    <span
                                        class="text-muted-foreground shrink-0 font-mono text-xs"
                                    >
                                        {{ candidate.sku ?? '—' }}
                                    </span>
                                </button>
                            </Form>
                        </li>
                    </ul>
                </div>
            </AdminCard>
        </div>
    </div>
</template>
