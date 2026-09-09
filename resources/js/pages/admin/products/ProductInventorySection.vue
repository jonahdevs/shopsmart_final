<script setup lang="ts">
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

type Option = { value: string; label: string };

/**
 * How much there is of it. The "Inventory" facet of the product data card.
 *
 * The SKU leads it rather than sitting with the name and the slug, because a
 * SKU is the warehouse's handle on the thing, not the shop's: it is what a
 * stock count is written against, and the questions either side of it here —
 * how many, when to warn, may it be oversold — are the ones it is asked with.
 * A variable product has none of its own; its variants carry theirs.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    stockStatusOptions: Option[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-1.5 sm:col-span-2">
            <Label for="sku">SKU</Label>
            <Input
                id="sku"
                name="sku"
                :default-value="product.sku ?? undefined"
                maxlength="255"
                placeholder="Leave blank on a variable product"
            />
            <InputError :message="errors.sku" />
        </div>

        <div class="space-y-1.5">
            <Label for="stock_status">Stock status</Label>
            <Select name="stock_status" :default-value="product.stockStatus">
                <SelectTrigger id="stock_status">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in stockStatusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
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

        <!--
          A raw checkbox rather than the ui/ one: the server reads an unticked
          box as absent, which is what a bare input sends and what the request's
          boolean casting is written against.
        -->
        <div class="flex items-center gap-2 sm:col-span-3">
            <input
                id="allow_backorder"
                name="allow_backorder"
                type="checkbox"
                value="1"
                :checked="product.allowBackorder"
                class="border-input size-4 rounded"
            />
            <Label for="allow_backorder">
                Allow backorders when it is out of stock
            </Label>
        </div>
    </div>
</template>
