<script setup lang="ts">
import { Banknote } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type IdOption = { value: number; label: string };

/**
 * Money, in whole KES.
 *
 * Nothing here converts anything. The server takes whole KES and stores integer
 * cents once, in the form request — arithmetic on this side is how the two
 * ends drift apart.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    taxClassOptions: IdOption[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Pricing" :icon="Banknote" />

        <div class="space-y-4 p-5">
            <p class="text-muted-foreground text-sm">
                Whole KES. Leave the price blank for price-on-application; the
                sale price is what the customer pays.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-1.5">
                    <Label for="price">Price</Label>
                    <Input
                        id="price"
                        name="price"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="product.price ?? undefined"
                    />
                    <InputError :message="errors.price" />
                </div>

                <div class="space-y-1.5">
                    <Label for="sale_price">Sale price</Label>
                    <Input
                        id="sale_price"
                        name="sale_price"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="product.salePrice ?? undefined"
                    />
                    <InputError :message="errors.sale_price" />
                </div>

                <div class="space-y-1.5">
                    <Label for="cost_price">Cost price</Label>
                    <Input
                        id="cost_price"
                        name="cost_price"
                        type="number"
                        step="0.01"
                        min="0"
                        :default-value="product.costPrice ?? undefined"
                    />
                    <InputError :message="errors.cost_price" />
                </div>

                <div class="space-y-1.5">
                    <Label for="tax_class_id">Tax class</Label>
                    <NativeSelect
                        id="tax_class_id"
                        name="tax_class_id"
                        :model-value="
                            product.taxClassId === null
                                ? ''
                                : String(product.taxClassId)
                        "
                    >
                        <option value="">Store default</option>
                        <option
                            v-for="option in taxClassOptions"
                            :key="option.value"
                            :value="String(option.value)"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.tax_class_id" />
                </div>

                <!--
                  A raw checkbox rather than the ui/ one: the server reads an
                  unticked box as absent, which is what a bare input sends and
                  what the request's boolean casting is written against.
                -->
                <div class="flex items-center gap-2 sm:col-span-2 sm:pt-6">
                    <input
                        id="is_taxable"
                        name="is_taxable"
                        type="checkbox"
                        value="1"
                        :checked="product.isTaxable"
                        class="border-input size-4 rounded"
                    />
                    <Label for="is_taxable">Charge tax on this product</Label>
                </div>
            </div>
        </div>
    </AdminCard>
</template>
