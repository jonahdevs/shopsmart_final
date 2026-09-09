<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ExternalLink,
    MoreHorizontal,
    Plus,
    SquarePen,
    Tags,
} from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { toUrl } from '@/lib/utils';
import { catalog } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as adminTagCreate,
    edit as adminTagEdit,
    index as adminTags,
} from '@/routes/admin/tags';
import { index as adminTagProducts } from '@/routes/admin/tags/products';

type TagFilters = {
    search: string | null;
    sort: string;
    direction: string;
};

const { tags, pagination, filters } = defineProps<{
    tags: App.Data.AdminTagRowData[];
    pagination: App.Data.PaginationData;
    filters: TagFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Tags', href: adminTags().url },
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
        toUrl: (query) => adminTags.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'name', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
        },
    });

/**
 * Where the shop shows this tag's products.
 *
 * The catalog listing filters on a tag by name, which is also how the home
 * page's rails resolve one — so this link is the same query a shopper's would
 * be, and it is the only way from here to see what the tag actually does.
 */
function shopHref(name: string): string {
    return toUrl(catalog.url({ query: { tag: name } }));
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Tags" />

        <AdminPageHeader
            title="Tags"
            :description="`${pagination.total} tag${pagination.total === 1 ? '' : 's'} products can be merchandised by.`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="adminTagCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New tag
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
                search-label="Search tags"
                :show-clear="isFiltered"
                @clear="clear"
            />

            <AdminEmptyState
                v-if="tags.length === 0"
                :icon="Tags"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching tags' : 'No tags yet'"
                :description="
                    isFiltered
                        ? 'No tags match this search.'
                        : 'Tags are the labels the home page rails and the catalog filter are built from.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="adminTagCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create a tag
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
                                label="Tag"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <!--
                              Sortable, unlike the product counts on the brands
                              and attributes tables. Those answer "can I delete
                              this?"; this one answers "which rail is empty?",
                              which is a question about the order of the list
                              rather than about one row.
                            -->
                            <AdminSortableHead
                                label="Products"
                                align="end"
                                class="text-right"
                                :href="sortHref('products_count')"
                                :sort="ariaSort('products_count')"
                            />
                            <TableHead class="w-0">
                                <span class="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="tag in tags" :key="tag.id">
                            <TableCell class="font-medium">
                                {{ tag.name }}
                                <span
                                    class="text-muted-foreground block font-mono text-xs"
                                >
                                    {{ tag.slug }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ tag.productCount }}
                            </TableCell>
                            <!--
                              A three-dots menu rather than a row of buttons,
                              the same shape the products table uses. Named per
                              row: twenty-five triggers all announced as
                              "Actions" are twenty-five identical controls to
                              anyone not reading the screen.

                              No `usePortalTheme()` here. The content teleports
                              to `document.body`, but the staff tokens are the
                              bare `:root` block rather than a scoped class, so
                              a portalled menu is already in the right palette.
                            -->
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Actions for ${tag.name}`"
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
                                        <!--
                                          First, because it is what this table
                                          exists to reach: the count beside it
                                          is a number a merchandiser cannot act
                                          on until they can see the list behind
                                          it.
                                        -->
                                        <DropdownMenuItem as-child>
                                            <Link
                                                class="block w-full"
                                                :href="adminTagProducts(tag.id)"
                                            >
                                                <Tags
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Manage products
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem as-child>
                                            <Link
                                                class="block w-full"
                                                :href="adminTagEdit(tag.id)"
                                            >
                                                <SquarePen
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                Rename
                                            </Link>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem as-child>
                                            <a
                                                class="block w-full"
                                                :href="shopHref(tag.name)"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <ExternalLink
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                                View in shop
                                                <span class="sr-only">
                                                    (opens in a new tab)
                                                </span>
                                            </a>
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
