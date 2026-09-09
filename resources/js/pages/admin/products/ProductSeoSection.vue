<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ProductSectionCard from './ProductSectionCard.vue';

/** How the product introduces itself to a search engine. */
const { product, errors } = defineProps<{
    product: App.Data.AdminProductFormData;
    errors: Record<string, string>;
}>();

/**
 * Folded until something has been overridden. Every field in here has a
 * sensible default — the name is the title, the short description is the
 * summary — so an open, empty SEO card mostly says "you have not done this
 * yet" about work that does not need doing.
 */
const hasOverrides = computed(
    () => Boolean(product.metaTitle) || Boolean(product.metaDescription),
);

const hasErrors = computed(() =>
    Boolean(
        errors.meta_title || errors.meta_description || errors.canonical_url,
    ),
);
</script>

<template>
    <ProductSectionCard
        title="Search listing"
        :icon="Search"
        :open="hasOverrides"
        :alerted="hasErrors"
    >
        <div class="grid gap-4 p-5 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="meta_title">Meta title</Label>
                <Input
                    id="meta_title"
                    name="meta_title"
                    :default-value="product.metaTitle ?? undefined"
                    maxlength="255"
                />
                <InputError :message="errors.meta_title" />
            </div>

            <div class="space-y-1.5">
                <Label for="canonical_url">Canonical URL</Label>
                <Input
                    id="canonical_url"
                    name="canonical_url"
                    type="url"
                    maxlength="500"
                />
                <InputError :message="errors.canonical_url" />
            </div>

            <div class="space-y-1.5 sm:col-span-2">
                <Label for="meta_description">Meta description</Label>
                <Textarea
                    id="meta_description"
                    name="meta_description"
                    :default-value="product.metaDescription ?? undefined"
                    maxlength="500"
                />
                <InputError :message="errors.meta_description" />
            </div>
        </div>
    </ProductSectionCard>
</template>
