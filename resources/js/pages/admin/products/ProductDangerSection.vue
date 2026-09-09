<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2, TriangleAlert } from '@lucide/vue';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import ProductSectionCard from './ProductSectionCard.vue';

/**
 * Its own `<Form>`, so like the image panel it lives outside the one that saves
 * the product. Deleting is not a field on the product.
 *
 * The only card on the screen that arrives folded regardless of its content: a
 * destructive button sitting open at the bottom of a form is a button somebody
 * eventually presses on the way to Save.
 */
defineProps<{
    /** The saved product's slug; this section never renders before one exists. */
    productSlug: string;
}>();
</script>

<template>
    <ProductSectionCard
        title="Remove this product"
        :icon="TriangleAlert"
        :open="false"
    >
        <div class="space-y-4 p-5">
            <p class="text-muted-foreground text-sm">
                It leaves the storefront immediately. The orders that sold it
                keep their own record of the sale, and it can be restored from
                the bin.
            </p>

            <Form
                v-bind="ProductController.destroy.form(productSlug)"
                v-slot="{ processing: deleting }"
            >
                <Button
                    type="submit"
                    variant="destructive"
                    :disabled="deleting"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Move to the bin
                </Button>
            </Form>
        </div>
    </ProductSectionCard>
</template>
