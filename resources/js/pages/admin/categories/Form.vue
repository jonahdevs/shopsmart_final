<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ListTree, Search, Tag, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as adminDashboard } from '@/routes/admin';
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
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Categories', href: adminCategories().url },
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
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New category' : category.name" />

        <!--
          The page header sits inside the form so Save stays an ordinary submit
          button instead of a detached one wired back by `form=`. The delete
          form below is a sibling, not a child — forms cannot nest.
        -->
        <Form
            v-bind="submitTarget"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                :title="isNew ? 'New category' : category.name"
                description="Leave the slug blank to have one made from the name."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminCategories()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ isNew ? 'Create category' : 'Save category' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              What the category *is* on the left, where it sits in the tree on
              the right. The split matters because the parent select is the one
              field a staff member changes without touching anything else.
            -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <AdminCard>
                        <AdminCardHeader title="Details" :icon="Tag" />

                        <div class="grid gap-4 p-5 sm:grid-cols-2">
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

                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    :default-value="
                                        category.description ?? undefined
                                    "
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
                                    :default-value="
                                        category.iconSvg ?? undefined
                                    "
                                    placeholder="<svg …></svg>"
                                />
                                <InputError :message="errors.icon_svg" />
                            </div>
                        </div>
                    </AdminCard>

                    <AdminCard>
                        <AdminCardHeader
                            title="Search listing"
                            :icon="Search"
                        />

                        <div class="grid gap-4 p-5">
                            <div class="space-y-1.5">
                                <Label for="meta_title">Meta title</Label>
                                <Input
                                    id="meta_title"
                                    name="meta_title"
                                    :default-value="
                                        category.metaTitle ?? undefined
                                    "
                                    maxlength="255"
                                />
                                <InputError :message="errors.meta_title" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="meta_description">
                                    Meta description
                                </Label>
                                <Textarea
                                    id="meta_description"
                                    name="meta_description"
                                    :default-value="
                                        category.metaDescription ?? undefined
                                    "
                                    maxlength="500"
                                />
                                <InputError
                                    :message="errors.meta_description"
                                />
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <div class="space-y-6">
                    <AdminCard>
                        <AdminCardHeader title="Placement" :icon="ListTree" />

                        <div class="space-y-4 p-5">
                            <p class="text-muted-foreground text-sm">
                                A category cannot be filed under itself or one
                                of its own subcategories — those are left out of
                                the parent list.
                            </p>

                            <div class="space-y-1.5">
                                <Label for="parent_id">Parent</Label>
                                <!--
                                  `null`, not `''`: reka-ui reserves the empty
                                  string for a cleared selection and refuses it
                                  as an item value. A null selection makes the
                                  hidden `<select>` the primitive posts fall
                                  back to its empty option, so the server still
                                  reads a blank field.
                                -->
                                <Select
                                    name="parent_id"
                                    :default-value="
                                        category.parentId === null
                                            ? undefined
                                            : String(category.parentId)
                                    "
                                >
                                    <SelectTrigger id="parent_id">
                                        <SelectValue placeholder="Top level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="null">
                                            Top level
                                        </SelectItem>
                                        <SelectItem
                                            v-for="option in parentOptions"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ indent(option.depth)
                                            }}{{ option.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.parent_id" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="status">Status</Label>
                                <Select
                                    name="status"
                                    :default-value="category.status"
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
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
                        </div>
                    </AdminCard>
                </div>
            </div>
        </Form>

        <AdminCard v-if="!isNew">
            <AdminCardHeader title="Delete this category" :icon="Trash2" />

            <div class="space-y-4 p-5">
                <p class="text-muted-foreground text-sm">
                    Products filed here are not deleted — they simply become
                    uncategorised. A category that still has subcategories is
                    refused; move or remove those first.
                </p>

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
            </div>
        </AdminCard>
    </div>
</template>
