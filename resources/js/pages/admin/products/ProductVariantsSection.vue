<script setup lang="ts">
import { Layers, Plus, Trash2 } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type Option = { value: string; label: string };

export type AttributeGroup = {
    label: string;
    options: { value: number; label: string }[];
};

/** A variant row while it is being edited; `id` is null until it is saved. */
export type VariantRow = {
    id: number | null;
    sku: string;
    barcode: string | null;
    price: number | null;
    salePrice: number | null;
    costPrice: number | null;
    stockStatus: string;
    stockQuantity: number | null;
    allowBackorder: boolean;
    isActive: boolean;
    sortOrder: number;
    attributeValueIds: number[];
};

defineProps<{
    stockStatusOptions: Option[];
    attributeGroups: AttributeGroup[];
    errors: Record<string, string>;
}>();

/**
 * The rows are the one thing on this screen that is not an uncontrolled input.
 *
 * How many variants exist is itself something the staff member edits, so the
 * array decides what renders while the inputs inside each row still carry the
 * values. The parent owns the array because it has to reseed it from the
 * server's answer after a save; this section only adds and removes rows.
 */
const rows = defineModel<VariantRow[]>({ required: true });

function addVariant(): void {
    rows.value.push({
        id: null,
        sku: '',
        barcode: null,
        price: null,
        salePrice: null,
        costPrice: null,
        stockStatus: 'in_stock',
        stockQuantity: null,
        allowBackorder: false,
        isActive: true,
        sortOrder: rows.value.length,
        attributeValueIds: [],
    });
}
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Variants" :icon="Layers">
            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addVariant"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Add variant
                </Button>
            </template>
        </AdminCardHeader>

        <AdminEmptyState
            v-if="rows.length === 0"
            :icon="Layers"
            title="No variants yet"
            description="Each variant has its own SKU and stock. A blank price inherits the product's."
        />

        <div v-else class="flex flex-col gap-4 p-5">
            <div
                v-for="(variant, index) in rows"
                :key="index"
                class="bg-muted/30 grid gap-4 rounded-md border p-4 sm:grid-cols-3"
            >
                <input
                    v-if="variant.id !== null"
                    type="hidden"
                    :name="`variants[${index}][id]`"
                    :value="variant.id"
                />

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-sku`">SKU</Label>
                    <Input
                        :id="`variant-${index}-sku`"
                        :name="`variants[${index}][sku]`"
                        :default-value="variant.sku"
                        required
                        maxlength="255"
                    />
                    <InputError :message="errors[`variants.${index}.sku`]" />
                </div>

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-price`">Price</Label>
                    <Input
                        :id="`variant-${index}-price`"
                        :name="`variants[${index}][price]`"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="variant.price ?? undefined"
                    />
                    <InputError :message="errors[`variants.${index}.price`]" />
                </div>

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-sale-price`">
                        Sale price
                    </Label>
                    <Input
                        :id="`variant-${index}-sale-price`"
                        :name="`variants[${index}][sale_price]`"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="variant.salePrice ?? undefined"
                    />
                    <InputError
                        :message="errors[`variants.${index}.sale_price`]"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-stock-status`">
                        Stock status
                    </Label>
                    <NativeSelect
                        :id="`variant-${index}-stock-status`"
                        :name="`variants[${index}][stock_status]`"
                        :model-value="variant.stockStatus"
                    >
                        <option
                            v-for="option in stockStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </div>

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-stock-quantity`">
                        On hand
                    </Label>
                    <Input
                        :id="`variant-${index}-stock-quantity`"
                        :name="`variants[${index}][stock_quantity]`"
                        type="number"
                        min="0"
                        :default-value="variant.stockQuantity ?? undefined"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label :for="`variant-${index}-options`">Options</Label>
                    <NativeSelect
                        :id="`variant-${index}-options`"
                        :name="`variants[${index}][attribute_value_ids][]`"
                        multiple
                        class="h-28"
                        :model-value="variant.attributeValueIds.map(String)"
                    >
                        <optgroup
                            v-for="group in attributeGroups"
                            :key="group.label"
                            :label="group.label"
                        >
                            <option
                                v-for="option in group.options"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </option>
                        </optgroup>
                    </NativeSelect>
                </div>

                <div class="flex items-center gap-4 sm:col-span-2 sm:pt-6">
                    <div class="flex items-center gap-2">
                        <input
                            :id="`variant-${index}-active`"
                            :name="`variants[${index}][is_active]`"
                            type="checkbox"
                            value="1"
                            :checked="variant.isActive"
                            class="border-input size-4 rounded"
                        />
                        <Label :for="`variant-${index}-active`">Active</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            :id="`variant-${index}-backorder`"
                            :name="`variants[${index}][allow_backorder]`"
                            type="checkbox"
                            value="1"
                            :checked="variant.allowBackorder"
                            class="border-input size-4 rounded"
                        />
                        <Label :for="`variant-${index}-backorder`">
                            Backorders
                        </Label>
                    </div>
                </div>

                <div class="flex items-end justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="rows.splice(index, 1)"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Remove
                    </Button>
                </div>
            </div>
        </div>
    </AdminCard>
</template>
