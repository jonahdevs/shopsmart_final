<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminProducts } from '@/routes/admin/products';
import ProductDangerSection from './ProductDangerSection.vue';
import ProductDataSection from './ProductDataSection.vue';
import ProductDescriptionSection from './ProductDescriptionSection.vue';
import ProductFilingSection from './ProductFilingSection.vue';
import ProductIdentitySection from './ProductIdentitySection.vue';
import type { LinkRow } from './ProductLinksSection.vue';
import ProductMediaSection from './ProductMediaSection.vue';
import ProductPublishSection from './ProductPublishSection.vue';
import ProductSeoSection from './ProductSeoSection.vue';
import type { AttributeGroup, VariantRow } from './ProductVariantsSection.vue';

type Option = { value: string; label: string };
type IdOption = { value: number; label: string };

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
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Products', href: adminProducts().url },
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
 * values. They live here rather than in the two panels that render them
 * because only this component sees the server's answer to a save.
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
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New product' : product.name" />

        <!--
          The page header sits inside the form so Save can be a real submit
          button with the form's own `processing` in scope. Pulling it out
          would mean wiring the two together through the `form` attribute and
          losing the disabled state.
        -->
        <Form
            v-bind="submitTarget"
            :options="{ preserveScroll: true, preserveState: true }"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
            @success="reseedFromProps"
        >
            <AdminPageHeader
                :title="isNew ? 'New product' : product.name"
                :description="
                    isNew
                        ? 'Prices are in whole KES.'
                        : `Editing ${product.slug}. Prices are in whole KES.`
                "
            >
                <template #actions>
                    <Button variant="ghost" as-child>
                        <Link :href="adminProducts()">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ isNew ? 'Create product' : 'Save product' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              Four cards down the main column and two beside them, following the
              reference build: what the product IS, how it TRADES, then the two
              long bodies of prose that only matter once both are settled.
              Publication and filing sit in the aside because neither is about
              the product — one is about the shop, the other about the shelf —
              and both are answered in a glance rather than filled in.
            -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <ProductIdentitySection
                        :product="product"
                        :errors="errors"
                    />

                    <ProductDataSection
                        v-model:variants="variants"
                        v-model:links="links"
                        :product="product"
                        :type-options="typeOptions"
                        :tax-class-options="taxClassOptions"
                        :stock-status-options="stockStatusOptions"
                        :link-type-options="linkTypeOptions"
                        :linkable-products="linkableProducts"
                        :attribute-groups="attributeGroups"
                        :errors="errors"
                    />

                    <ProductDescriptionSection
                        :product="product"
                        :errors="errors"
                    />

                    <ProductSeoSection :product="product" :errors="errors" />
                </div>

                <div class="space-y-6">
                    <ProductPublishSection
                        :product="product"
                        :status-options="statusOptions"
                        :visibility-options="visibilityOptions"
                        :errors="errors"
                    />

                    <ProductFilingSection
                        :product="product"
                        :category-options="categoryOptions"
                        :brand-options="brandOptions"
                        :errors="errors"
                    />
                </div>
            </div>
        </Form>

        <!--
          The reference build keeps images in its sidebar, next to the brand and
          the category. Ours cannot: both of these panels post on their own
          route, forms do not nest, and the aside above is inside the one that
          saves the product. So they continue the same two-and-one grid below
          it, and they only exist once the product does — an image has nothing
          to attach to until then.
        -->
        <div v-if="!isNew" class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <ProductMediaSection
                    :media="product.media"
                    :product-slug="productSlug"
                />
            </div>

            <ProductDangerSection :product-slug="productSlug" />
        </div>
    </div>
</template>
