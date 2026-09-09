<script setup lang="ts">
import { FolderTree } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ProductSectionCard from './ProductSectionCard.vue';

type IdOption = { value: number; label: string };

/** U+00A0. A browser collapses the ordinary leading whitespace in markup. */
const NBSP = String.fromCharCode(160);

/**
 * Where the product sits in the taxonomy.
 *
 * Brand, category and tags stay one card rather than the three the reference
 * build splits them into: its cards are foldable strips holding a single
 * control each, and three headers for three selects is chrome, not structure.
 * Sort order has left for the product data card's advanced facet — filing says
 * where a product belongs, not where it queues among the ones it belongs with.
 */
const { product } = defineProps<{
    product: App.Data.AdminProductFormData;
    categoryOptions: App.Data.AdminCategoryOptionData[];
    brandOptions: IdOption[];
    errors: Record<string, string>;
}>();

/**
 * Indent a row so a flat select still reads as the category tree.
 *
 * Non-breaking spaces: a browser collapses the ordinary leading whitespace in
 * markup, which is exactly the whitespace carrying the depth.
 */
function indent(depth: number): string {
    return NBSP.repeat(depth * 2);
}

const tagList = computed(() => product.tags.join(', '));
</script>

<template>
    <ProductSectionCard title="Filing" :icon="FolderTree">
        <div class="grid gap-4 p-5">
            <p class="text-muted-foreground text-sm">
                The primary category is filed alongside any extras chosen here.
            </p>

            <div class="space-y-1.5">
                <Label for="brand_id">Brand</Label>
                <!--
                  `null`, not `''`: reka-ui reserves the empty string for a
                  cleared selection and refuses it as an item value. A null
                  selection makes the hidden `<select>` the primitive posts fall
                  back to its empty option, so the server still reads a blank
                  field.
                -->
                <Select
                    name="brand_id"
                    :default-value="
                        product.brandId === null
                            ? undefined
                            : String(product.brandId)
                    "
                >
                    <SelectTrigger id="brand_id">
                        <SelectValue placeholder="No brand" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="null">No brand</SelectItem>
                        <SelectItem
                            v-for="option in brandOptions"
                            :key="option.value"
                            :value="String(option.value)"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.brand_id" />
            </div>

            <div class="space-y-1.5">
                <Label for="primary_category_id">Primary category</Label>
                <Select
                    name="primary_category_id"
                    :default-value="
                        product.primaryCategoryId === null
                            ? undefined
                            : String(product.primaryCategoryId)
                    "
                >
                    <SelectTrigger id="primary_category_id">
                        <SelectValue placeholder="Uncategorised" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="null">Uncategorised</SelectItem>
                        <SelectItem
                            v-for="option in categoryOptions"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ indent(option.depth) }}{{ option.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.primary_category_id" />
            </div>

            <div class="space-y-1.5">
                <Label for="categories">Also filed in</Label>
                <!--
                  Still a native multi-select. reka-ui's Select submits through a
                  hidden single `<select>` whose value cannot carry an array, so a
                  dropdown here would post nothing at all for `categories[]`.
                -->
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
        </div>
    </ProductSectionCard>
</template>
