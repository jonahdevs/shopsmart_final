<script setup lang="ts">
import { FileText } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import ProductSectionCard from './ProductSectionCard.vue';

/** The prose the product page is built from. */
const { product, errors } = defineProps<{
    product: App.Data.AdminProductFormData;
    errors: Record<string, string>;
}>();

/**
 * Folded away until there is prose to show, which on a new product is never.
 * Three tall textareas are most of the height of this screen, and a staff
 * member adding a product to the catalog in a hurry is not writing copy in the
 * same sitting — they come back for it, and then this card is the one already
 * open.
 */
const hasContent = computed(
    () =>
        Boolean(product.shortDescription) ||
        Boolean(product.description) ||
        Boolean(product.technicalSpecification),
);

const hasErrors = computed(() =>
    Boolean(
        errors.short_description ||
        errors.description ||
        errors.technical_specification,
    ),
);
</script>

<template>
    <ProductSectionCard
        title="Description"
        :icon="FileText"
        :open="hasContent"
        :alerted="hasErrors"
    >
        <div class="grid gap-4 p-5">
            <div class="space-y-1.5">
                <Label for="short_description">Short description</Label>
                <Textarea
                    id="short_description"
                    name="short_description"
                    :default-value="product.shortDescription ?? undefined"
                    maxlength="500"
                />
                <InputError :message="errors.short_description" />
            </div>

            <div class="space-y-1.5">
                <Label for="description">Full description</Label>
                <Textarea
                    id="description"
                    name="description"
                    class="min-h-40"
                    :default-value="product.description ?? undefined"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="space-y-1.5">
                <Label for="technical_specification">
                    Technical specification
                </Label>
                <Textarea
                    id="technical_specification"
                    name="technical_specification"
                    class="min-h-32"
                    :default-value="product.technicalSpecification ?? undefined"
                />
                <InputError :message="errors.technical_specification" />
            </div>
        </div>
    </ProductSectionCard>
</template>
