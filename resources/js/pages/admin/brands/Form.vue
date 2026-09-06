<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
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
import { Textarea } from '@/components/ui/textarea';
import { index as adminBrands } from '@/routes/admin/brands';

const props = defineProps<{
    brand: App.Data.AdminBrandFormData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Brands', href: '/admin/brands' },
        ],
    },
});

const isNew = computed(() => props.brand.id === null);

/** Empty while the brand does not exist yet — nothing renders it then. */
const brandSlug = computed(() => props.brand.slug ?? '');

const submitTarget = computed(() =>
    props.brand.slug === null
        ? BrandController.store.form()
        : BrandController.update.form(props.brand.slug),
);
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="isNew ? 'New brand' : brand.name" />

        <AdminPageHeader
            :title="isNew ? 'New brand' : brand.name"
            description="Leave the slug blank to have one made from the name."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="adminBrands()">
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        All brands
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
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="brand.name"
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
                            :default-value="brand.slug ?? undefined"
                            maxlength="255"
                            placeholder="made-from-the-name"
                        />
                        <InputError :message="errors.slug" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="website_url">Website</Label>
                        <Input
                            id="website_url"
                            name="website_url"
                            type="url"
                            :default-value="brand.websiteUrl ?? undefined"
                            maxlength="500"
                        />
                        <InputError :message="errors.website_url" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="sort_order">Sort order</Label>
                        <Input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            :default-value="brand.sortOrder"
                        />
                        <InputError :message="errors.sort_order" />
                    </div>

                    <div class="flex items-center gap-2 sm:col-span-2">
                        <input
                            id="is_active"
                            name="is_active"
                            type="checkbox"
                            value="1"
                            :checked="brand.isActive"
                            class="border-input size-4 rounded"
                        />
                        <Label for="is_active">
                            Active — shown on the storefront
                        </Label>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            :default-value="brand.description ?? undefined"
                            maxlength="5000"
                        />
                        <InputError :message="errors.description" />
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
                            :default-value="brand.metaTitle ?? undefined"
                            maxlength="255"
                        />
                        <InputError :message="errors.meta_title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="meta_description">Meta description</Label>
                        <Textarea
                            id="meta_description"
                            name="meta_description"
                            :default-value="brand.metaDescription ?? undefined"
                            maxlength="500"
                        />
                        <InputError :message="errors.meta_description" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ isNew ? 'Create brand' : 'Save brand' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminBrands()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <Card v-if="!isNew">
            <CardHeader>
                <CardTitle>Delete this brand</CardTitle>
                <CardDescription>
                    Its products are not deleted — they simply become unbranded.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="BrandController.destroy.form(brandSlug)"
                    v-slot="{ processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete brand
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
