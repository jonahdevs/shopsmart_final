<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Images, Trash2 } from '@lucide/vue';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Images post on their own route rather than as part of the save: a multipart
 * body cannot ride the PATCH, and a dropped file should land straight away
 * rather than on the next save.
 *
 * That is why this section carries its own `<Form>`s and therefore has to sit
 * outside the one that saves the product — forms do not nest.
 */
defineProps<{
    media: App.Data.AdminProductMediaData[];
    /** The saved product's slug; this section never renders before one exists. */
    productSlug: string;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Images" :icon="Images" />

        <AdminEmptyState
            v-if="media.length === 0"
            :icon="Images"
            title="No images yet"
        />

        <div v-else class="flex flex-wrap gap-3 p-5">
            <figure
                v-for="image in media"
                :key="image.id"
                class="w-32 space-y-2"
            >
                <img
                    :src="image.thumbUrl"
                    :alt="image.name"
                    class="aspect-square w-32 rounded-md border object-cover"
                />
                <Form
                    v-bind="
                        ProductController.destroyMedia.form([
                            productSlug,
                            image.id,
                        ])
                    "
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing: removing }"
                >
                    <Button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        :disabled="removing"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Remove
                    </Button>
                </Form>
            </figure>
        </div>

        <Form
            v-bind="ProductController.storeMedia.form(productSlug)"
            :options="{ preserveScroll: true }"
            class="flex flex-wrap items-end gap-3 border-t p-5"
            v-slot="{ errors: mediaErrors, processing: uploading }"
        >
            <div class="space-y-1.5">
                <Label for="images">Add images</Label>
                <Input
                    id="images"
                    name="images[]"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                />
                <p class="text-muted-foreground text-xs">
                    JPG, PNG or WebP, up to 5 MB each.
                </p>
                <InputError
                    :message="mediaErrors.images ?? mediaErrors['images.0']"
                />
            </div>
            <Button type="submit" variant="outline" :disabled="uploading">
                Upload
            </Button>
        </Form>
    </AdminCard>
</template>
