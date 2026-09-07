<script setup lang="ts">
import { Globe } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

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
    <AdminCard>
        <AdminCardHeader title="Publication" :icon="Globe" />

        <div class="grid gap-4 p-5">
            <p class="text-muted-foreground text-sm">
                A scheduled product needs a time to go live at.
            </p>

            <div class="space-y-1.5">
                <Label for="status">Status</Label>
                <NativeSelect
                    id="status"
                    name="status"
                    :model-value="product.status"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.status" />
            </div>

            <div class="space-y-1.5">
                <Label for="visibility">Visibility</Label>
                <NativeSelect
                    id="visibility"
                    name="visibility"
                    :model-value="product.visibility"
                >
                    <option
                        v-for="option in visibilityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
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
    </AdminCard>
</template>
