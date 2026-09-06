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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Brands', href: '/admin/brands' },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered table has to be a shareable link, and staff expect the back button
 * to undo a filter.
 */
const form = ref({
    search: filters.search ?? '',
    active: filters.active ?? '',
});

function activeQuery(overrides: Record<string, string | number> = {}) {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (filters.sort !== 'name' || filters.direction !== 'asc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    return { ...query, ...overrides };
}

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminBrands.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminBrands.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminBrands.url({ query: activeQuery({ sort: column, direction }) });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
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

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="brand-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="brand-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Name or slug"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="brand-active">Availability</Label>
                        <NativeSelect id="brand-active" v-model="form.active">
                            <option value="">All brands</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </NativeSelect>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="brands.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No brands match these filters.
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
                                        Brand
                                    </Link>
                                </TableHead>
                                <TableHead>Website</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Products</TableHead>
                                <TableHead
                                    class="text-right"
                                    :aria-sort="ariaSort('sort_order')"
                                >
                                    <Link
                                        :href="sortHref('sort_order')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        Order
                                    </Link>
                                </TableHead>
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
                                    <Badge
                                        :variant="brand.isActive ? 'default' : 'outline'"
                                    >
                                        {{ brand.isActive ? 'Active' : 'Inactive' }}
                                    </Badge>
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
            </CardContent>
        </Card>

        <AdminPagination :pagination="pagination" :href-for-page="hrefForPage" />
    </div>
</template>
