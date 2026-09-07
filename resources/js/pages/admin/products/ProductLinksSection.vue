<script setup lang="ts">
import { Link2, Plus, Trash2 } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

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
    <AdminCard>
        <AdminCardHeader title="Related products" :icon="Link2">
            <template #actions>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addLink"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Add link
                </Button>
            </template>
        </AdminCardHeader>

        <AdminEmptyState
            v-if="rows.length === 0"
            :icon="Link2"
            title="Nothing is linked to this product yet"
            description="Upsells, cross-sells, accessories and spare parts. Required accessories come pre-ticked on the storefront's prompt."
        />

        <div v-else class="flex flex-col gap-4 p-5">
            <div
                v-for="(link, index) in rows"
                :key="index"
                class="bg-muted/30 grid gap-4 rounded-md border p-4 sm:grid-cols-4"
            >
                <div class="space-y-1.5">
                    <Label :for="`link-${index}-type`">Type</Label>
                    <NativeSelect
                        :id="`link-${index}-type`"
                        :name="`links[${index}][type]`"
                        :model-value="link.type"
                    >
                        <option
                            v-for="option in linkTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                </div>

                <div class="space-y-1.5 sm:col-span-2">
                    <Label :for="`link-${index}-product`">Product</Label>
                    <NativeSelect
                        :id="`link-${index}-product`"
                        :name="`links[${index}][linked_product_id]`"
                        :model-value="
                            link.linkedProductId === null
                                ? ''
                                : String(link.linkedProductId)
                        "
                    >
                        <option value="">Choose a product</option>
                        <option
                            v-for="option in linkableProducts"
                            :key="option.value"
                            :value="String(option.value)"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
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
    </AdminCard>
</template>
