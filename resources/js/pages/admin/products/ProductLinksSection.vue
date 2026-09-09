<script setup lang="ts">
import { Link2, Plus, Trash2 } from '@lucide/vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
type IdOption = { value: number; label: string };

/** A link row while it is being edited. */
export type LinkRow = {
    type: string;
    linkedProductId: number | null;
    isRequired: boolean;
    defaultQuantity: number;
    sortOrder: number;
};

defineProps<{
    linkTypeOptions: Option[];
    linkableProducts: IdOption[];
    errors: Record<string, string>;
}>();

/** Same arrangement as the variant repeater: the parent owns the array. */
const rows = defineModel<LinkRow[]>({ required: true });

function addLink(): void {
    rows.value.push({
        type: 'upsell',
        linkedProductId: null,
        isRequired: false,
        defaultQuantity: 1,
        sortOrder: rows.value.length,
    });
}
</script>

<template>
    <div>
        <!--
          The panel's own toolbar, the way the reference build's linked-products
          tab carries one. The card header above belongs to all six facets, so
          an action that only makes sense while this one is showing cannot live
          there.
        -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b p-5"
        >
            <p class="text-muted-foreground text-sm">
                Upsells, cross-sells, accessories and spare parts. Required
                accessories come pre-ticked on the storefront's prompt.
            </p>

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="addLink"
            >
                <Plus class="size-4" aria-hidden="true" />
                Add link
            </Button>
        </div>

        <AdminEmptyState
            v-if="rows.length === 0"
            :icon="Link2"
            title="Nothing is linked to this product yet"
        />

        <div v-else class="flex flex-col gap-4 p-5">
            <div
                v-for="(link, index) in rows"
                :key="index"
                class="bg-muted/30 grid gap-4 rounded-md border p-4 sm:grid-cols-4"
            >
                <div class="space-y-1.5">
                    <Label :for="`link-${index}-type`">Type</Label>
                    <Select
                        :name="`links[${index}][type]`"
                        :default-value="link.type"
                    >
                        <SelectTrigger :id="`link-${index}-type`">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in linkTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-1.5 sm:col-span-2">
                    <Label :for="`link-${index}-product`">Product</Label>
                    <!--
                      `null`, not `''`: reka-ui reserves the empty string for a
                      cleared selection and refuses it as an item value. A null
                      selection makes the hidden `<select>` the primitive posts fall
                      back to its empty option, so the server still reads a blank
                      field.
                    -->
                    <Select
                        :name="`links[${index}][linked_product_id]`"
                        :default-value="
                            link.linkedProductId === null
                                ? undefined
                                : String(link.linkedProductId)
                        "
                    >
                        <SelectTrigger :id="`link-${index}-product`">
                            <SelectValue placeholder="Choose a product" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="null">
                                Choose a product
                            </SelectItem>
                            <SelectItem
                                v-for="option in linkableProducts"
                                :key="option.value"
                                :value="String(option.value)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="errors[`links.${index}.linked_product_id`]"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label :for="`link-${index}-quantity`">
                        Default quantity
                    </Label>
                    <Input
                        :id="`link-${index}-quantity`"
                        :name="`links[${index}][default_quantity]`"
                        type="number"
                        min="1"
                        :default-value="link.defaultQuantity"
                    />
                </div>

                <div class="flex items-center gap-2 sm:col-span-3">
                    <input
                        :id="`link-${index}-required`"
                        :name="`links[${index}][is_required]`"
                        type="checkbox"
                        value="1"
                        :checked="link.isRequired"
                        class="border-input size-4 rounded"
                    />
                    <Label :for="`link-${index}-required`">
                        Required accessory
                    </Label>
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
    </div>
</template>
