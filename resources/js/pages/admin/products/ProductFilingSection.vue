<script setup lang="ts">
import { FolderTree } from '@lucide/vue';
import { computed } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type IdOption = { value: number; label: string };

/** U+00A0. A browser collapses ordinary leading whitespace inside an option. */
const NBSP = String.fromCharCode(160);

/** Where the product sits in the taxonomy. */
const { product } = defineProps<{
    product: App.Data.AdminProductFormData;
    categoryOptions: App.Data.AdminCategoryOptionData[];
    brandOptions: IdOption[];
    errors: Record<string, string>;
}>();

/**
 * Indent an option so a flat select still reads as the category tree.
 *
 * Non-breaking spaces: a browser collapses ordinary leading whitespace inside
 * an option, which is exactly the whitespace carrying the depth.
 */
function indent(depth: number): string {
    return NBSP.repeat(depth * 2);
}

const tagList = computed(() => product.tags.join(', '));
</script>

<template>
    <AdminCard>
        <AdminCardHeader title="Filing" :icon="FolderTree" />

        <div class="grid gap-4 p-5">
            <p class="text-muted-foreground text-sm">
                The primary category is filed alongside any extras chosen here.
            </p>

            <div class="space-y-1.5">
                <Label for="brand_id">Brand</Label>
                <NativeSelect
                    id="brand_id"
                    name="brand_id"
                    :model-value="
                        product.brandId === null ? '' : String(product.brandId)
                    "
                >
                    <option value="">No brand</option>
                    <option
                        v-for="option in brandOptions"
                        :key="option.value"
                        :value="String(option.value)"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.brand_id" />
            </div>

            <div class="space-y-1.5">
                <Label for="primary_category_id">Primary category</Label>
                <NativeSelect
                    id="primary_category_id"
                    name="primary_category_id"
                    :model-value="
                        product.primaryCategoryId === null
                            ? ''
                            : String(product.primaryCategoryId)
                    "
                >
                    <option value="">Uncategorised</option>
                    <option
                        v-for="option in categoryOptions"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ indent(option.depth) }}{{ option.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.primary_category_id" />
            </div>

            <div class="space-y-1.5">
                <Label for="categories">Also filed in</Label>
                <NativeSelect
                    id="categories"
                    name="categories[]"
                    multiple
                    class="h-40"
                    :model-value="product.categoryIds.map(String)"
                >
                    <option
                        v-for="option in categoryOptions"
                        :key="option.id"
                        :value="String(option.id)"
                    >
                        {{ indent(option.depth) }}{{ option.name }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.categories" />
            </div>

            <div class="space-y-1.5">
                <Label for="tags">Tags</Label>
                <Input
                    id="tags"
                    name="tags"
                    :default-value="tagList"
                    maxlength="500"
                    placeholder="Comma separated"
                />
                <InputError :message="errors.tags" />
            </div>

            <div class="space-y-1.5">
                <Label for="sort_order">Sort order</Label>
                <Input
                    id="sort_order"
                    name="sort_order"
                    type="number"
                    min="0"
                    :default-value="product.sortOrder"
                />
                <InputError :message="errors.sort_order" />
            </div>
        </div>
    </AdminCard>
</template>
