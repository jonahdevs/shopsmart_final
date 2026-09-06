<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index as adminCategories } from '@/routes/admin/categories';

/** U+00A0. A browser collapses ordinary leading whitespace inside an option. */
const NBSP = String.fromCharCode(160);

const props = defineProps<{
    category: App.Data.AdminCategoryFormData;
    parentOptions: App.Data.AdminCategoryOptionData[];
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

const isNew = computed(() => props.category.id === null);

/** Empty while the category does not exist yet — nothing renders it then. */
const categorySlug = computed(() => props.category.slug ?? '');

const submitTarget = computed(() =>
    props.category.slug === null
        ? CategoryController.store.form()
        : CategoryController.update.form(props.category.slug),
);

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
        <Head :title="isNew ? 'New category' : category.name" />

        <AdminPageHeader
            :title="isNew ? 'New category' : category.name"
            description="Leave the slug blank to have one made from the name."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="adminCategories()">
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        All categories
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Form
            v-bind="submitTarget"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        A category cannot be filed under itself or one of its
                        own subcategories — those are left out of the parent
                        list.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="category.name"
                            required
                            maxlength="255"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="slug">Slug</Label>
                        <Input
                            id="slug"
                            name="slug"
                            :default-value="category.slug ?? undefined"
                            maxlength="255"
                            placeholder="made-from-the-name"
                        />
                        <InputError :message="errors.slug" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="parent_id">Parent</Label>
                        <NativeSelect
                            id="parent_id"
                            name="parent_id"
                            :model-value="
                                category.parentId === null
                                    ? ''
                                    : String(category.parentId)
                            "
                        >
                            <option value="">Top level</option>
                            <option
                                v-for="option in parentOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ indent(option.depth) }}{{ option.name }}
                            </option>
                        </NativeSelect>
                        <InputError :message="errors.parent_id" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="status">Status</Label>
                        <NativeSelect
                            id="status"
                            name="status"
                            :model-value="category.status"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                        <InputError :message="errors.status" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="sort_order">Sort order</Label>
                        <Input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="category.sortOrder"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="category.description ?? undefined"
                            maxlength="5000"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="icon_svg">Icon (inline SVG)</Label>
                        <Textarea
                            id="icon_svg"
                            name="icon_svg"
                            class="font-mono text-xs"
                            :default-value="category.iconSvg ?? undefined"
                            placeholder="<svg …></svg>"
                        />
                        <InputError :message="errors.icon_svg" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Search listing</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="meta_title">Meta title</Label>
                        <Input
                            id="meta_title"
                            name="meta_title"
                            :default-value="category.metaTitle ?? undefined"
                            maxlength="255"
                        />
                        <InputError :message="errors.meta_title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="meta_description">Meta description</Label>
                        <Textarea
                            id="meta_description"
                            name="meta_description"
                            :default-value="category.metaDescription ?? undefined"
                            maxlength="500"
                        />
                        <InputError :message="errors.meta_description" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ isNew ? 'Create category' : 'Save category' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminCategories()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <Card v-if="!isNew">
            <CardHeader>
                <CardTitle>Delete this category</CardTitle>
                <CardDescription>
                    Products filed here are not deleted — they simply become
                    uncategorised. A category that still has subcategories is
                    refused; move or remove those first.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="CategoryController.destroy.form(categorySlug)"
                    v-slot="{ errors: deleteErrors, processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete category
                    </Button>
                    <InputError class="mt-2" :message="deleteErrors.category" />
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
