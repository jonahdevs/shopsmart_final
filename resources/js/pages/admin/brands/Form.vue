<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Globe, Search, Tag, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminBrands } from '@/routes/admin/brands';

const props = defineProps<{
    brand: App.Data.AdminBrandFormData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Brands', href: adminBrands().url },
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
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New brand' : brand.name" />

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
                eyebrow="Catalog"
                :title="isNew ? 'New brand' : brand.name"
                description="Leave the slug blank to have one made from the name."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminBrands()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ isNew ? 'Create brand' : 'Save brand' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              What the brand *is* on the left, whether shoppers can see it on
              the right — the two questions are answered at different times, so
              the switch does not sit buried among the copy fields.
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

                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="website_url">Website</Label>
                                <Input
                                    id="website_url"
                                    name="website_url"
                                    type="url"
                                    :default-value="
                                        brand.websiteUrl ?? undefined
                                    "
                                    maxlength="500"
                                />
                                <InputError :message="errors.website_url" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    :default-value="
                                        brand.description ?? undefined
                                    "
                                    maxlength="5000"
                                />
                                <InputError :message="errors.description" />
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
                                        brand.metaTitle ?? undefined
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
                                        brand.metaDescription ?? undefined
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
                        <AdminCardHeader title="Storefront" :icon="Globe" />

                        <div class="space-y-4 p-5">
                            <div class="flex items-center gap-2">
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
                        </div>
                    </AdminCard>
                </div>
            </div>
        </Form>

        <AdminCard v-if="!isNew">
            <AdminCardHeader title="Delete this brand" :icon="Trash2" />

            <div class="space-y-4 p-5">
                <p class="text-muted-foreground text-sm">
                    Its products are not deleted — they simply become unbranded.
                </p>

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
            </div>
        </AdminCard>
    </div>
</template>
