<script setup lang="ts">
import { Info } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ProductSectionCard from './ProductSectionCard.vue';

/**
 * What the product is called and how it is addressed.
 *
 * Three fields, deliberately: this is the card a staff member lands on, and it
 * asks only the questions that are answered before anything else is known
 * about the product. The SKU left for the inventory facet and the type for the
 * product data card's header, because neither is a name — one is the
 * warehouse's handle and the other decides what the rest of the form offers.
 *
 * Like every partial on this screen it holds nothing. The fields are
 * uncontrolled `name`d inputs contributed to the one `<Form>` that wraps the
 * whole page, which reads them out of the DOM at submit time — so a section is
 * markup and props, and the `name` attributes are the server contract.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    errors: Record<string, string>;
}>();
</script>

<template>
    <ProductSectionCard title="Basics" :icon="Info">
        <div class="space-y-4 p-5">
            <p class="text-muted-foreground text-sm">
                Leave the slug blank to have one made from the name.
            </p>

            <div class="space-y-1.5">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="product.name"
                    required
                    maxlength="255"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="slug">Slug</Label>
                    <Input
                        id="slug"
                        name="slug"
                        :default-value="product.slug ?? undefined"
                        maxlength="255"
                        placeholder="made-from-the-name"
                    />
                    <InputError :message="errors.slug" />
                </div>

                <div class="space-y-1.5">
                    <Label for="model_number">Model number</Label>
                    <Input
                        id="model_number"
                        name="model_number"
                        :default-value="product.modelNumber ?? undefined"
                        maxlength="255"
                    />
                    <InputError :message="errors.model_number" />
                </div>
            </div>
        </div>
    </ProductSectionCard>
</template>
