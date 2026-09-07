<script setup lang="ts">
import { FileText } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** The prose the product page is built from. */
defineProps<{
    product: App.Data.AdminProductFormData;
    errors: Record<string, string>;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Description" :icon="FileText" />

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
    </AdminCard>
</template>
