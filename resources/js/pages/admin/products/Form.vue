<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index as adminProducts } from '@/routes/admin/products';

type Option = { value: string; label: string };
type IdOption = { value: number; label: string };
type AttributeGroup = { label: string; options: IdOption[] };

/** U+00A0. A browser collapses ordinary leading whitespace inside an option. */
const NBSP = String.fromCharCode(160);

/** A variant row while it is being edited; `id` is null until it is saved. */
type VariantRow = {
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

/** A link row while it is being edited. */
type LinkRow = {
    type: string;
    linkedProductId: number | null;
    isRequired: boolean;
    defaultQuantity: number;
    sortOrder: number;
};

const props = defineProps<{
    product: App.Data.AdminProductFormData;
    statusOptions: Option[];
    visibilityOptions: Option[];
    typeOptions: Option[];
    stockStatusOptions: Option[];
    linkTypeOptions: Option[];
    categoryOptions: App.Data.AdminCategoryOptionData[];
    brandOptions: IdOption[];
    taxClassOptions: IdOption[];
    attributeGroups: AttributeGroup[];
    linkableProducts: IdOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/admin' },
            { title: 'Products', href: '/admin/products' },
        ],
    },
});

const isNew = computed(() => props.product.id === null);

/**
 * The slug the media and delete routes are addressed by. Empty while the
 * product does not exist yet, which is exactly when nothing renders it — a
 * plain string keeps those Wayfinder calls typed without a null assertion.
 */
const productSlug = computed(() => props.product.slug ?? '');

/**
 * Create posts, edit patches. Wayfinder builds both, so the page never spells
 * a URL or a method itself.
 */
const submitTarget = computed(() =>
    props.product.slug === null
        ? ProductController.store.form()
        : ProductController.update.form(props.product.slug),
);

/**
 * The repeaters are the only local state on this page.
 *
 * Every other field is an uncontrolled `name`d input the enclosing `<Form>`
 * reads out of the DOM at submit time. Variants and links cannot be, because
 * how many rows exist is itself something the staff member edits — so these
 * arrays decide what renders, and the inputs inside each row still carry the
 * values.
 */
const variants = ref<VariantRow[]>(props.product.variants.map(toVariantRow));
const links = ref<LinkRow[]>(props.product.links.map(toLinkRow));

function toVariantRow(variant: App.Data.AdminProductVariantData): VariantRow {
    return {
        id: variant.id,
        sku: variant.sku,
        barcode: variant.barcode,
        price: variant.price,
        salePrice: variant.salePrice,
        costPrice: variant.costPrice,
        stockStatus: variant.stockStatus,
        stockQuantity: variant.stockQuantity,
        allowBackorder: variant.allowBackorder,
        isActive: variant.isActive,
        sortOrder: variant.sortOrder,
        attributeValueIds: variant.attributeValueIds,
    };
}

function toLinkRow(link: App.Data.AdminProductLinkData): LinkRow {
    return {
        type: link.type,
        linkedProductId: link.linkedProductId,
        isRequired: link.isRequired,
        defaultQuantity: link.defaultQuantity,
        sortOrder: link.sortOrder,
    };
}

function addVariant(): void {
    variants.value.push({
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
        sortOrder: variants.value.length,
        attributeValueIds: [],
    });
}

function addLink(): void {
    links.value.push({
        type: 'upsell',
        linkedProductId: null,
        isRequired: false,
        defaultQuantity: 1,
        sortOrder: links.value.length,
    });
}

/**
 * Re-read the repeaters from the server's answer once a save has landed.
 *
 * The form runs with `preserveState`, so a rejected save keeps the rows the
 * staff member added rather than snapping back to what is stored. That is
 * exactly wrong after a *successful* save, when the new rows now have ids —
 * without this the next save would create them a second time.
 */
function reseedFromProps(): void {
    variants.value = props.product.variants.map(toVariantRow);
    links.value = props.product.links.map(toLinkRow);
}

/**
 * Indent an option so a flat select still reads as the category tree.
 *
 * Non-breaking spaces: a browser collapses ordinary leading whitespace inside
 * an option, which is exactly the whitespace carrying the depth.
 */
function indent(depth: number): string {
    return NBSP.repeat(depth * 2);
}

const tagList = computed(() => props.product.tags.join(', '));
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head :title="isNew ? 'New product' : product.name" />

        <AdminPageHeader
            :title="isNew ? 'New product' : product.name"
            :description="
                isNew
                    ? 'Prices are in whole KES.'
                    : `Editing ${product.slug}. Prices are in whole KES.`
            "
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="adminProducts()">
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        All products
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Form
            v-bind="submitTarget"
            :options="{ preserveScroll: true, preserveState: true }"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
            @success="reseedFromProps"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Identity</CardTitle>
                    <CardDescription>
                        Leave the slug blank to have one made from the name.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Publication</CardTitle>
                    <CardDescription>
                        A scheduled product needs a time to go live at.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Pricing</CardTitle>
                    <CardDescription>
                        Whole KES. Leave the price blank for
                        price-on-application; the sale price is what the
                        customer pays.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Stock and shipping</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
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

                    <div
                        class="flex flex-col justify-center gap-2 sm:col-span-2"
                    >
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
                            <Label for="requires_shipping">
                                Requires shipping
                            </Label>
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Filing</CardTitle>
                    <CardDescription>
                        The primary category is filed alongside any extras
                        chosen here.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="brand_id">Brand</Label>
                        <NativeSelect
                            id="brand_id"
                            name="brand_id"
                            :model-value="
                                product.brandId === null
                                    ? ''
                                    : String(product.brandId)
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
                        <Label for="primary_category_id">
                            Primary category
                        </Label>
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="short_description">Short description</Label>
                        <Textarea
                            id="short_description"
                            name="short_description"
                            :default-value="product.shortDescription ?? undefined"
                            maxlength="500"
                        />
                        <InputError :message="errors.short_description" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="description">Full description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            class="min-h-40"
                            :default-value="product.description ?? undefined"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="technical_specification">
                            Technical specification
                        </Label>
                        <Textarea
                            id="technical_specification"
                            name="technical_specification"
                            class="min-h-32"
                            :default-value="
                                product.technicalSpecification ?? undefined
                            "
                        />
                        <InputError :message="errors.technical_specification" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Search listing</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="meta_title">Meta title</Label>
                        <Input
                            id="meta_title"
                            name="meta_title"
                            :default-value="product.metaTitle ?? undefined"
                            maxlength="255"
                        />
                        <InputError :message="errors.meta_title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="canonical_url">Canonical URL</Label>
                        <Input
                            id="canonical_url"
                            name="canonical_url"
                            type="url"
                            maxlength="500"
                        />
                        <InputError :message="errors.canonical_url" />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="meta_description">Meta description</Label>
                        <Textarea
                            id="meta_description"
                            name="meta_description"
                            :default-value="product.metaDescription ?? undefined"
                            maxlength="500"
                        />
                        <InputError :message="errors.meta_description" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Variants</CardTitle>
                    <CardDescription>
                        Each variant has its own SKU and stock. A blank price
                        inherits the product's.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p
                        v-if="variants.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        This product has no variants.
                    </p>

                    <div
                        v-for="(variant, index) in variants"
                        :key="index"
                        class="grid gap-4 rounded-md border p-4 sm:grid-cols-3"
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
                            <InputError
                                :message="errors[`variants.${index}.sku`]"
                            />
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
                            <InputError
                                :message="errors[`variants.${index}.price`]"
                            />
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
                            <Label :for="`variant-${index}-options`">
                                Options
                            </Label>
                            <NativeSelect
                                :id="`variant-${index}-options`"
                                :name="`variants[${index}][attribute_value_ids][]`"
                                multiple
                                class="h-28"
                                :model-value="
                                    variant.attributeValueIds.map(String)
                                "
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

                        <div
                            class="flex items-center gap-4 sm:col-span-2 sm:pt-6"
                        >
                            <div class="flex items-center gap-2">
                                <input
                                    :id="`variant-${index}-active`"
                                    :name="`variants[${index}][is_active]`"
                                    type="checkbox"
                                    value="1"
                                    :checked="variant.isActive"
                                    class="border-input size-4 rounded"
                                />
                                <Label :for="`variant-${index}-active`">
                                    Active
                                </Label>
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
                                @click="variants.splice(index, 1)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        </div>
                    </div>

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addVariant"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Add variant
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Related products</CardTitle>
                    <CardDescription>
                        Upsells, cross-sells, accessories and spare parts.
                        Required accessories come pre-ticked on the storefront's
                        prompt.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <p
                        v-if="links.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nothing is linked to this product yet.
                    </p>

                    <div
                        v-for="(link, index) in links"
                        :key="index"
                        class="grid gap-4 rounded-md border p-4 sm:grid-cols-4"
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
                                :message="
                                    errors[`links.${index}.linked_product_id`]
                                "
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
                                @click="links.splice(index, 1)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        </div>
                    </div>

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addLink"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Add link
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ isNew ? 'Create product' : 'Save product' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminProducts()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <!--
            Images post on their own route rather than as part of the save: a
            multipart body cannot ride the PATCH, and a dropped file should land
            straight away rather than on the next save.
        -->
        <Card v-if="!isNew">
            <CardHeader>
                <CardTitle>Images</CardTitle>
                <CardDescription>
                    JPG, PNG or WebP, up to 5 MB each.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div v-if="product.media.length" class="flex flex-wrap gap-3">
                    <figure
                        v-for="image in product.media"
                        :key="image.id"
                        class="w-32 space-y-2"
                    >
                        <img
                            :src="image.thumbUrl"
                            :alt="image.name"
                            class="aspect-square w-32 rounded-md border object-cover"
                        />
                        <Form
                            v-bind="
                                ProductController.destroyMedia.form([
                                    productSlug,
                                    image.id,
                                ])
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing: removing }"
                        >
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                :disabled="removing"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        </Form>
                    </figure>
                </div>

                <Form
                    v-bind="ProductController.storeMedia.form(productSlug)"
                    :options="{ preserveScroll: true }"
                    class="flex flex-wrap items-end gap-3"
                    v-slot="{ errors: mediaErrors, processing: uploading }"
                >
                    <div class="space-y-1.5">
                        <Label for="images">Add images</Label>
                        <Input
                            id="images"
                            name="images[]"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                        />
                        <InputError
                            :message="mediaErrors.images ?? mediaErrors['images.0']"
                        />
                    </div>
                    <Button type="submit" variant="outline" :disabled="uploading">
                        Upload
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <Card v-if="!isNew">
            <CardHeader>
                <CardTitle>Remove this product</CardTitle>
                <CardDescription>
                    It leaves the storefront immediately. The orders that sold
                    it keep their own record of the sale, and it can be restored
                    from the bin.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="ProductController.destroy.form(productSlug)"
                    v-slot="{ processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Move to the bin
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
