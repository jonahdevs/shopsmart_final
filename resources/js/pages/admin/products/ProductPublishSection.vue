<script setup lang="ts">
import { Globe } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ProductSectionCard from './ProductSectionCard.vue';

type Option = { value: string; label: string };

/**
 * Whether the product is on the shop floor, and to whom.
 *
 * It leads the aside rather than sitting in the main column because it is the
 * decision a staff member comes back to the page for — everything below it is
 * the detail of a product that is already published or already a draft.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    statusOptions: Option[];
    visibilityOptions: Option[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <ProductSectionCard title="Publication" :icon="Globe">
        <div class="grid gap-4 p-5">
            <p class="text-muted-foreground text-sm">
                A scheduled product needs a time to go live at.
            </p>

            <div class="space-y-1.5">
                <Label for="status">Status</Label>
                <Select name="status" :default-value="product.status">
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
                <Label for="visibility">Visibility</Label>
                <Select name="visibility" :default-value="product.visibility">
                    <SelectTrigger id="visibility">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in visibilityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.visibility" />
            </div>

            <div class="space-y-1.5">
                <Label for="published_at">Publish at</Label>
                <Input
                    id="published_at"
                    name="published_at"
                    type="datetime-local"
                    :default-value="product.publishedAt ?? undefined"
                />
                <InputError :message="errors.published_at" />
            </div>
        </div>
    </ProductSectionCard>
</template>
