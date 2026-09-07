<script setup lang="ts">
import { Info } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type Option = { value: string; label: string };

/**
 * What the product is called and how it is addressed.
 *
 * Like every partial on this screen it holds nothing. The fields are
 * uncontrolled `name`d inputs contributed to the one `<Form>` that wraps the
 * whole page, which reads them out of the DOM at submit time — so a section is
 * markup and props, and the `name` attributes are the server contract.
 */
defineProps<{
    product: App.Data.AdminProductFormData;
    typeOptions: Option[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Identity" :icon="Info" />

        <div class="space-y-4 p-5">
            <p class="text-muted-foreground text-sm">
                Leave the slug blank to have one made from the name.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5 sm:col-span-2">
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
                    <Label for="model_number">Model number</Label>
                    <Input
                        id="model_number"
                        name="model_number"
                        :default-value="product.modelNumber ?? undefined"
                        maxlength="255"
                    />
                    <InputError :message="errors.model_number" />
                </div>

                <div class="space-y-1.5">
                    <Label for="type">Type</Label>
                    <NativeSelect
                        id="type"
                        name="type"
                        :model-value="product.type"
                    >
                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.type" />
                </div>
            </div>
        </div>
    </AdminCard>
</template>
