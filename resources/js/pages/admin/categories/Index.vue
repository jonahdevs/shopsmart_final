<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Boxes,
    ChevronRight,
    ExternalLink,
    FolderTree,
    MoreHorizontal,
    Plus,
    SquarePen,
} from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminFilterSelect from '@/components/admin/AdminFilterSelect.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
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
import { toUrl } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminCategoryCreate,
    edit as adminCategoryEdit,
    index as adminCategories,
} from '@/routes/admin/categories';
import { index as adminProducts } from '@/routes/admin/products';
import { show as storefrontCategory } from '@/routes/category';

type CategoryFilters = {
    search: string | null;
    status: string | null;
};

const { categories, filters, statusOptions } = defineProps<{
    categories: App.Data.AdminCategoryRowData[];
    filters: CategoryFilters;
    statusOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Categories', href: adminCategories().url },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter. `useIndexTable` owns the debounce, the visit options and
 * the rule that empty filters are omitted rather than sent blank.
 *
 * The tree is neither sorted nor paged — the controller walks it depth-first
 * from the roots, because paging a tree would cut it across a parent and the
 * shape is the information here. So the sort state is a constant that matches
 * the declared default, which is what keeps `sort` out of the URL entirely,
 * and neither `sortHref` nor `hrefForPage` is taken.
 */
const { form, isFiltered, clear } = useIndexTable({
    toUrl: (query) => adminCategories.url({ query }),
    sortState: () => ({ sort: 'sort_order', direction: 'asc' }),
    defaultSort: { column: 'sort_order', direction: 'asc' },
    fields: {
        search: filters.search ?? '',
        status: filters.status ?? '',
    },
});

/**
 * The tree is a flat table indented by depth. Padding rather than nested
 * markup, so a row is still a row and the columns stay in line.
 */
function indentStyle(depth: number): Record<string, string> {
    return { paddingLeft: `${depth * 1.5}rem` };
}

/*
  Typed against the generated enum, so a status renamed on the server fails the
  build here rather than silently offering a link to a 404.
*/
const LIVE: App.Enums.CategoryStatus = 'active';

/** Whether `category.show` would render this row rather than abort. */
function isLive(category: App.Data.AdminCategoryRowData): boolean {
    return category.status === LIVE;
}

/** The products table, narrowed to one category — the id is what it filters on. */
function productsHref(categoryId: number): string {
    return adminProducts.url({ query: { category: String(categoryId) } });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Categories" />

        <AdminPageHeader
            title="Categories"
            :description="`${categories.length} categor${categories.length === 1 ? 'y' : 'ies'} in the tree.`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="adminCategoryCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New category
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!--
          One card, two strips: filters and the tree. Not two cards — they are
          one object, and the border between them says so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Name or slug"
                search-label="Search categories"
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
            </AdminFilterBar>

            <AdminEmptyState
                v-if="categories.length === 0"
                :icon="FolderTree"
                :filtered="isFiltered"
                :title="
                    isFiltered ? 'No matching categories' : 'No categories yet'
                "
                :description="
                    isFiltered
                        ? 'No categories match these filters.'
                        : 'Categories are how shoppers browse. Start with a root.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="adminCategoryCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create a category
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
                            <TableHead>Category</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">
                                Subcategories
                            </TableHead>
                            <TableHead class="text-right">Products</TableHead>
                            <TableHead class="text-right">Order</TableHead>
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="category in categories"
                            :key="category.id"
                        >
                            <TableCell class="font-medium">
                                <span
                                    class="flex items-center gap-1.5"
                                    :style="indentStyle(category.depth)"
                                >
                                    <ChevronRight
                                        v-if="category.depth > 0"
                                        class="text-muted-foreground size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ category.name }}
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ category.slug }}
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="category.statusLabel"
                                    :variant="category.statusVariant"
                                />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ category.childCount }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ category.productCount }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ category.sortOrder }}
                            </TableCell>
                            <!--
                              A three-dots menu rather than a lone Edit button,
                              the same shape the products table uses. The
                              product count two columns left was a number with
                              nothing behind it until this menu: the same
                              complaint the products index tiles answer, that a
                              figure a manager cannot click through to has told
                              them something and denied them the work.

                              Delete is deliberately not here. It is a hard
                              delete, it is refused when the category still has
                              children, and the editor's delete card says both
                              of those things next to the button — a menu item
                              can say neither.

                              No `usePortalTheme()`. The content teleports to
                              `document.body`, but the staff tokens are the bare
                              `:root` block rather than a scoped class, so a
                              portalled menu is already in the right palette.
                            -->
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Actions for ${category.name}`"
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
                                                    adminCategoryEdit(
                                                        category.slug,
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

                                        <DropdownMenuItem as-child>
                                            <Link
                                                class="block w-full"
                                                :href="
                                                    productsHref(category.id)
                                                "
                                            >
                                                <Boxes
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                View products
                                                <span
                                                    class="text-muted-foreground ml-auto text-xs tabular-nums"
                                                >
                                                    {{ category.productCount }}
                                                </span>
                                            </Link>
                                        </DropdownMenuItem>

                                        <!--
                                          Offered only on an active category.
                                          `category.show` aborts on every other
                                          state, staff included — there is no
                                          preview here the way there is for a
                                          draft product — so the item states the
                                          reason rather than leading to a 404.
                                        -->
                                        <DropdownMenuItem
                                            v-if="isLive(category)"
                                            as-child
                                        >
                                            <a
                                                class="block w-full"
                                                :href="
                                                    toUrl(
                                                        storefrontCategory(
                                                            category.slug,
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
                                                View on store
                                                <span class="sr-only">
                                                    (opens in a new tab)
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
                                                {{ category.statusLabel }}
                                            </span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </AdminTable>
            </div>
        </AdminCard>
    </div>
</template>
