<script setup lang="ts">
import { Warehouse } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type Option = { value: string; label: string };

/** How much there is of it and whether it moves in a box. */
defineProps<{
    product: App.Data.AdminProductFormData;
    stockStatusOptions: Option[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Stock and shipping" :icon="Warehouse" />

        <div class="grid gap-4 p-5 sm:grid-cols-3">
            <div class="space-y-1.5">
                <Label for="stock_status">Stock status</Label>
                <NativeSelect
                    id="stock_status"
                    name="stock_status"
                    :model-value="product.stockStatus"
                >
                    <option
                        v-for="option in stockStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.stock_status" />
            </div>

            <div class="space-y-1.5">
                <Label for="stock_quantity">On hand</Label>
                <Input
                    id="stock_quantity"
                    name="stock_quantity"
                    type="number"
                    min="0"
                    :default-value="product.stockQuantity ?? undefined"
                    placeholder="Blank means untracked"
                />
                <InputError :message="errors.stock_quantity" />
            </div>

            <div class="space-y-1.5">
                <Label for="low_stock_threshold">Low stock at</Label>
                <Input
                    id="low_stock_threshold"
                    name="low_stock_threshold"
                    type="number"
                    min="0"
                    :default-value="product.lowStockThreshold ?? undefined"
                />
                <InputError :message="errors.low_stock_threshold" />
            </div>

            <div class="space-y-1.5">
                <Label for="min_order_quantity">Minimum order</Label>
                <Input
                    id="min_order_quantity"
                    name="min_order_quantity"
                    type="number"
                    min="1"
                    :default-value="product.minOrderQuantity ?? undefined"
                />
                <InputError :message="errors.min_order_quantity" />
            </div>

            <div class="flex flex-col justify-center gap-2 sm:col-span-2">
                <div class="flex items-center gap-2">
                    <input
                        id="allow_backorder"
                        name="allow_backorder"
                        type="checkbox"
                        value="1"
                        :checked="product.allowBackorder"
                        class="border-input size-4 rounded"
                    />
                    <Label for="allow_backorder">Allow backorders</Label>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="requires_shipping"
                        name="requires_shipping"
                        type="checkbox"
                        value="1"
                        :checked="product.requiresShipping"
                        class="border-input size-4 rounded"
                    />
                    <Label for="requires_shipping">Requires shipping</Label>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="is_virtual"
                        name="is_virtual"
                        type="checkbox"
                        value="1"
                        :checked="product.isVirtual"
                        class="border-input size-4 rounded"
                    />
                    <Label for="is_virtual">
                        Virtual (a service, never shipped)
                    </Label>
                </div>
            </div>
        </div>
    </AdminCard>
</template>
