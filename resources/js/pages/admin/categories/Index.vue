<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, FolderTree, Plus } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
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
    create as adminCategoryCreate,
    edit as adminCategoryEdit,
    index as adminCategories,
} from '@/routes/admin/categories';

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
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Categories" />

        <AdminPageHeader
            eyebrow="Catalog"
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
                <Table>
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
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="adminCategoryEdit(category.slug)"
                                    >
                                        Edit
                                        <span class="sr-only">
                                            {{ category.name }}
                                        </span>
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </AdminCard>
    </div>
</template>
