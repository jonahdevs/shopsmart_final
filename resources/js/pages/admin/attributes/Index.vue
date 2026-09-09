<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, SlidersHorizontal } from '@lucide/vue';
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
    create as adminAttributeCreate,
    edit as adminAttributeEdit,
    index as adminAttributes,
} from '@/routes/admin/attributes';

type AttributeFilters = {
    search: string | null;
    type: string | null;
    active: string | null;
    sort: string;
    direction: string;
};

const { attributes, pagination, filters, typeOptions } = defineProps<{
    attributes: App.Data.AdminAttributeRowData[];
    pagination: App.Data.PaginationData;
    filters: AttributeFilters;
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Attributes', href: adminAttributes().url },
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
        toUrl: (query) => adminAttributes.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'sort_order', direction: 'asc' },
        fields: {
            search: filters.search ?? '',
            type: filters.type ?? '',
            active: filters.active ?? '',
        },
    });
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Attributes" />

        <AdminPageHeader
            title="Attributes"
            :description="`${pagination.total} attribute${pagination.total === 1 ? '' : 's'} products can vary on.`"
        >
            <template #actions>
                <Button as-child>
                    <Link :href="adminAttributeCreate()">
                        <Plus class="size-4" aria-hidden="true" />
                        New attribute
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
                search-label="Search attributes"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <AdminFilterSelect
                    v-model="form.type"
                    class="w-40"
                    label="Renders as"
                    all-label="Any"
                >
                    <SelectItem
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </AdminFilterSelect>

                <AdminFilterSelect
                    v-model="form.active"
                    class="w-40"
                    label="Availability"
                    all-label="All attributes"
                >
                    <SelectItem value="1">Active</SelectItem>
                    <SelectItem value="0">Inactive</SelectItem>
                </AdminFilterSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="attributes.length === 0"
                :icon="SlidersHorizontal"
                :filtered="isFiltered"
                :title="
                    isFiltered ? 'No matching attributes' : 'No attributes yet'
                "
                :description="
                    isFiltered
                        ? 'No attributes match these filters.'
                        : 'Attributes are the axes a product varies on — size, colour, finish.'
                "
            >
                <template #action>
                    <Button as-child>
                        <Link :href="adminAttributeCreate()">
                            <Plus class="size-4" aria-hidden="true" />
                            Create an attribute
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
                                label="Attribute"
                                :href="sortHref('name')"
                                :sort="ariaSort('name')"
                            />
                            <TableHead>Renders as</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Values</TableHead>
                            <TableHead class="text-right">Used by</TableHead>
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
                        <TableRow
                            v-for="attribute in attributes"
                            :key="attribute.id"
                        >
                            <TableCell class="font-medium">
                                {{ attribute.name }}
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ attribute.slug }}
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ attribute.typeLabel }}
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="
                                        attribute.isActive
                                            ? 'Active'
                                            : 'Inactive'
                                    "
                                    :variant="
                                        attribute.isActive
                                            ? 'default'
                                            : 'outline'
                                    "
                                />
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ attribute.valueCount }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ attribute.productCount }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ attribute.sortOrder }}
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="adminAttributeEdit(attribute.id)"
                                    >
                                        Edit
                                        <span class="sr-only">
                                            {{ attribute.name }}
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
