<script setup lang="ts">
import {
    Banknote,
    Boxes,
    Layers,
    Link2,
    SlidersHorizontal,
    Truck,
    Warehouse,
    type LucideIcon,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { adminTones } from '@/components/admin/tones';
import InputError from '@/components/InputError.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ProductAdvancedSection from './ProductAdvancedSection.vue';
import ProductInventorySection from './ProductInventorySection.vue';
import type { LinkRow } from './ProductLinksSection.vue';
import ProductLinksSection from './ProductLinksSection.vue';
import ProductPricingSection from './ProductPricingSection.vue';
import ProductSectionCard from './ProductSectionCard.vue';
import ProductShippingSection from './ProductShippingSection.vue';
import type { AttributeGroup, VariantRow } from './ProductVariantsSection.vue';
import ProductVariantsSection from './ProductVariantsSection.vue';

type Option = { value: string; label: string };
type IdOption = { value: number; label: string };

/**
 * Everything about the product *as a thing that is sold*, behind one rail.
 *
 * The old screen laid pricing, stock, variants and related products out as four
 * more cards in the scroll, which meant a staff member fixing one price scrolled
 * past three panels they were not there for. These six facets answer to the same
 * question — how does this product trade — and only one of them is wanted at a
 * time, so they share a card and take turns.
 *
 * The type selector belongs in this card's header rather than with the name and
 * the slug, because type is not a fact about the product's identity: it is the
 * switch that decides what this card even offers. A simple product has no
 * variants to show.
 *
 * Panels are never unmounted — `unmount-on-hide` is off, and every field on the
 * screen is an uncontrolled `name`d input the page's `<Form>` reads out of the
 * DOM at submit time. A tab that dropped its fields while it was not looked at
 * would silently post nothing for them, which would make choosing a tab a way of
 * editing the product.
 */
const { errors, product } = defineProps<{
    product: App.Data.AdminProductFormData;
    typeOptions: Option[];
    taxClassOptions: IdOption[];
    stockStatusOptions: Option[];
    linkTypeOptions: Option[];
    linkableProducts: IdOption[];
    attributeGroups: AttributeGroup[];
    errors: Record<string, string>;
}>();

const variants = defineModel<VariantRow[]>('variants', { required: true });
const links = defineModel<LinkRow[]>('links', { required: true });

/**
 * Which fields each tab is answerable for, so a validation message that came
 * back from the server can be shown on the tab holding it. Prefixes: a key is
 * a match when it is the prefix itself or sits under it (`variants.0.sku`).
 */
const tabFields: Record<string, string[]> = {
    pricing: [
        'price',
        'sale_price',
        'cost_price',
        'is_taxable',
        'tax_class_id',
    ],
    inventory: [
        'sku',
        'stock_status',
        'stock_quantity',
        'allow_backorder',
        'low_stock_threshold',
        'min_order_quantity',
    ],
    shipping: ['is_virtual', 'requires_shipping'],
    variants: ['variants'],
    linked: ['links'],
    advanced: ['sort_order'],
};

const tabs: { value: string; label: string; icon: LucideIcon }[] = [
    { value: 'pricing', label: 'Pricing', icon: Banknote },
    { value: 'inventory', label: 'Inventory', icon: Warehouse },
    { value: 'shipping', label: 'Shipping', icon: Truck },
    { value: 'variants', label: 'Variants', icon: Layers },
    { value: 'linked', label: 'Linked products', icon: Link2 },
    { value: 'advanced', label: 'Advanced', icon: SlidersHorizontal },
];

/**
 * The chosen type, watched only so the rail can react to it.
 *
 * The select itself stays uncontrolled — nothing here writes back into it, and
 * what gets posted is still whatever the primitive's hidden `<select>` holds.
 * This is a read of that choice, not a second copy of it.
 */
const currentType = ref(product.type);

/**
 * Variants are a variable product's business. The tab is offered anyway once
 * rows exist, because a product that was variable and has been switched to
 * simple still has those rows on the form, they will still be posted, and
 * hiding the only place they can be deleted would strand them.
 */
const visibleTabs = computed(() =>
    tabs.filter(
        (tab) =>
            tab.value !== 'variants' ||
            currentType.value === 'variable' ||
            variants.value.length > 0,
    ),
);

const activeTab = ref('pricing');

function errorsOn(tab: string): boolean {
    return Object.keys(errors).some((key) =>
        tabFields[tab].some(
            (field) => key === field || key.startsWith(`${field}.`),
        ),
    );
}

const firstTabInError = computed(
    () => visibleTabs.value.find((tab) => errorsOn(tab.value))?.value,
);

/**
 * A rejected save lands on the tab that was rejected. Otherwise the message
 * renders inside a panel nobody is looking at, and the form appears to have
 * refused to save for no reason at all.
 */
watch(firstTabInError, (tab) => {
    if (tab) {
        activeTab.value = tab;
    }
});

/**
 * The reference build calls this `ensureValidTab`: changing the type can take
 * away the tab you are standing on, and a rail with nothing selected shows an
 * empty card.
 */
watch(visibleTabs, (available) => {
    if (!available.some((tab) => tab.value === activeTab.value)) {
        activeTab.value = available[0].value;
    }
});

/**
 * Open the tab holding a field the *browser* has just refused.
 *
 * A `required` field the browser cannot show is a submit that fails with only a
 * console warning to explain it. Constraint validation has already aborted this
 * attempt by the time we get here, so this does not rescue the press — it puts
 * the offending field on screen so the next one works. `invalid` does not
 * bubble, hence the capture phase.
 */
function revealPanelOf(event: Event): void {
    const target = event.target as HTMLElement | null;
    const panel = target?.closest?.('[data-tab-panel]');
    const value = panel?.getAttribute('data-tab-panel');

    if (value) {
        activeTab.value = value;
    }
}
</script>

<template>
    <ProductSectionCard title="Product data" :icon="Boxes">
        <template #actions>
            <Select
                name="type"
                :default-value="product.type"
                @update:model-value="
                    (value) => (currentType = String(value ?? ''))
                "
            >
                <SelectTrigger
                    id="type"
                    size="sm"
                    class="w-36"
                    aria-label="Product type"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </template>

        <InputError
            v-if="errors.type"
            :message="errors.type"
            class="px-5 pt-4"
        />

        <div @invalid.capture="revealPanelOf">
            <Tabs
                v-model="activeTab"
                orientation="vertical"
                :unmount-on-hide="false"
                class="gap-0 md:flex-row"
            >
                <!--
                  A rail, not the segmented control the primitive dresses itself
                  as by default: six labelled facets read down the side of the
                  card the way the reference build's do, and a segmented control
                  that long wraps into an unreadable block.
                -->
                <TabsList
                    class="flex h-auto w-full shrink-0 flex-col items-stretch justify-start rounded-none border-b bg-transparent p-0 md:w-48 md:border-r md:border-b-0"
                >
                    <TabsTrigger
                        v-for="tab in visibleTabs"
                        :key="tab.value"
                        :value="tab.value"
                        class="group text-muted-foreground data-[state=active]:text-foreground dark:text-muted-foreground dark:data-[state=active]:text-foreground relative h-auto w-full flex-none justify-start gap-2 rounded-none border-0 px-4 py-2.5 text-left data-[state=active]:bg-transparent data-[state=active]:shadow-none dark:data-[state=active]:bg-transparent"
                    >
                        <!--
                          The active marker is its own element rather than a
                          left border, because the primitive's dark-mode active
                          state sets a border colour of its own and would win.
                        -->
                        <span
                            class="group-data-[state=active]:bg-primary absolute inset-y-0 left-0 w-0.5"
                            aria-hidden="true"
                        />
                        <component
                            :is="tab.icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ tab.label }}</span>
                        <span
                            v-if="errorsOn(tab.value)"
                            class="ml-auto size-1.5 shrink-0 rounded-full bg-current"
                            :class="adminTones.danger.text"
                            aria-hidden="true"
                        />
                    </TabsTrigger>
                </TabsList>

                <div class="min-w-0 flex-1">
                    <TabsContent
                        value="pricing"
                        data-tab-panel="pricing"
                        class="p-5"
                    >
                        <ProductPricingSection
                            :product="product"
                            :tax-class-options="taxClassOptions"
                            :errors="errors"
                        />
                    </TabsContent>

                    <TabsContent
                        value="inventory"
                        data-tab-panel="inventory"
                        class="p-5"
                    >
                        <ProductInventorySection
                            :product="product"
                            :stock-status-options="stockStatusOptions"
                            :errors="errors"
                        />
                    </TabsContent>

                    <TabsContent
                        value="shipping"
                        data-tab-panel="shipping"
                        class="p-5"
                    >
                        <ProductShippingSection :product="product" />
                    </TabsContent>

                    <TabsContent value="variants" data-tab-panel="variants">
                        <ProductVariantsSection
                            v-model="variants"
                            :stock-status-options="stockStatusOptions"
                            :attribute-groups="attributeGroups"
                            :errors="errors"
                        />
                    </TabsContent>

                    <TabsContent value="linked" data-tab-panel="linked">
                        <ProductLinksSection
                            v-model="links"
                            :link-type-options="linkTypeOptions"
                            :linkable-products="linkableProducts"
                            :errors="errors"
                        />
                    </TabsContent>

                    <TabsContent
                        value="advanced"
                        data-tab-panel="advanced"
                        class="p-5"
                    >
                        <ProductAdvancedSection
                            :product="product"
                            :errors="errors"
                        />
                    </TabsContent>
                </div>
            </Tabs>
        </div>
    </ProductSectionCard>
</template>
