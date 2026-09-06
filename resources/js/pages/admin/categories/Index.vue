<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Plus, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
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
import { toBadgeVariant } from '@/lib/utils';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Categories', href: '/admin/categories' },
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
    status: filters.status ?? '',
});

/** Only the filters actually set, so two identical views share one URL. */
function activeQuery() {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    return query;
}

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminCategories.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

/**
 * The tree is a flat table indented by depth. Padding rather than nested
 * markup, so a row is still a row and the columns stay in line.
 */
function indentStyle(depth: number): Record<string, string> {
    return { paddingLeft: `${depth * 1.5}rem` };
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
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

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="category-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="category-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Name or slug"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="category-status">Status</Label>
                        <NativeSelect id="category-status" v-model="form.status">
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
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="categories.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No categories match these filters.
                </p>

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
                                    <Badge
                                        :variant="toBadgeVariant(category.statusVariant)"
                                    >
                                        {{ category.statusLabel }}
                                    </Badge>
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
            </CardContent>
        </Card>
    </div>
</template>
