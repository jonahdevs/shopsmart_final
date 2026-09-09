<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { List, Plus, SlidersHorizontal, Tag, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AttributeController from '@/actions/App/Http/Controllers/Admin/AttributeController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminAttributes } from '@/routes/admin/attributes';

/** A value row while it is being edited; `id` is null until it is saved. */
type ValueRow = {
    id: number | null;
    value: string;
    label: string;
    slug: string;
    colorCode: string | null;
    sortOrder: number;
    isActive: boolean;
    variantCount: number;
};

const props = defineProps<{
    attribute: App.Data.AdminAttributeFormData;
    typeOptions: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Attributes', href: adminAttributes().url },
        ],
    },
});

const isNew = computed(() => props.attribute.id === null);

/** Zero while the attribute does not exist yet — nothing renders it then. */
const attributeId = computed(() => props.attribute.id ?? 0);

const submitTarget = computed(() =>
    props.attribute.id === null
        ? AttributeController.store.form()
        : AttributeController.update.form(props.attribute.id),
);

/**
 * The values repeater is the only local state on this page — every other field
 * is an uncontrolled `name`d input the enclosing `<Form>` reads out of the DOM.
 * How many value rows exist is itself something the staff member edits, so this
 * array decides what renders while the inputs inside each row carry the values.
 */
const values = ref<ValueRow[]>(props.attribute.values.map(toValueRow));

function toValueRow(value: App.Data.AdminAttributeValueData): ValueRow {
    return {
        id: value.id,
        value: value.value,
        label: value.label,
        slug: value.slug,
        colorCode: value.colorCode,
        sortOrder: value.sortOrder,
        isActive: value.isActive,
        variantCount: value.variantCount,
    };
}

function addValue(): void {
    values.value.push({
        id: null,
        value: '',
        label: '',
        slug: '',
        colorCode: null,
        sortOrder: values.value.length,
        isActive: true,
        variantCount: 0,
    });
}

/**
 * Re-read the rows from the server's answer once a save has landed, so newly
 * created values pick up their ids — otherwise the next save would create them
 * a second time. A rejected save keeps what the staff member typed.
 */
function reseedFromProps(): void {
    values.value = props.attribute.values.map(toValueRow);
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New attribute' : attribute.name" />

        <!--
          The page header sits inside the form so Save stays an ordinary submit
          button instead of a detached one wired back by `form=`. The delete
          form below is a sibling, not a child — forms cannot nest.
        -->
        <Form
            v-bind="submitTarget"
            :options="{ preserveScroll: true, preserveState: true }"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
            @success="reseedFromProps"
        >
            <AdminPageHeader
                :title="isNew ? 'New attribute' : attribute.name"
                description="An attribute and its values are saved together."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminAttributes()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ isNew ? 'Create attribute' : 'Save attribute' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <AdminCard>
                        <AdminCardHeader title="Details" :icon="Tag" />

                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    :default-value="attribute.name"
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
                                    :default-value="attribute.slug ?? undefined"
                                    maxlength="255"
                                    placeholder="made-from-the-name"
                                />
                                <InputError :message="errors.slug" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="type">Renders as</Label>
                                <Select
                                    name="type"
                                    :default-value="attribute.type"
                                >
                                    <SelectTrigger id="type">
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
                                <InputError :message="errors.type" />
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <div class="space-y-6">
                    <AdminCard>
                        <AdminCardHeader
                            title="Availability"
                            :icon="SlidersHorizontal"
                        />

                        <div class="space-y-4 p-5">
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    :checked="attribute.isActive"
                                    class="border-input size-4 rounded"
                                />
                                <Label for="is_active">
                                    Active — offered when building variants
                                </Label>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="sort_order">Sort order</Label>
                                <Input
                                    id="sort_order"
                                    name="sort_order"
                                    type="number"
                                    min="0"
                                    :default-value="attribute.sortOrder"
                                />
                                <InputError :message="errors.sort_order" />
                            </div>
                        </div>
                    </AdminCard>
                </div>
            </div>

            <!--
              The repeater is full width rather than in the grid above: a value
              row carries six fields, and squeezing it into two thirds of the
              page is what forced the old four-column jumble.
            -->
            <AdminCard>
                <AdminCardHeader title="Values" :icon="List">
                    <template #actions>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addValue"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Add value
                        </Button>
                    </template>
                </AdminCardHeader>

                <div class="border-b px-5 py-3">
                    <p class="text-muted-foreground text-sm">
                        A value that still defines a purchasable variant cannot
                        be removed; the save is refused rather than unpicking
                        the variant.
                    </p>
                    <InputError class="mt-2" :message="errors.values" />
                </div>

                <AdminEmptyState
                    v-if="values.length === 0"
                    :icon="List"
                    title="No values yet"
                    description="Add a value for every option a variant can be built on."
                />

                <!--
                  Each row is a strip of the card rather than a bordered box
                  inside it: the rows are one list, and a rule between them says
                  that where a gap and a second border does not.
                -->
                <div
                    v-for="(row, index) in values"
                    :key="index"
                    class="border-b px-5 py-4 last:border-b-0"
                >
                    <input
                        v-if="row.id !== null"
                        type="hidden"
                        :name="`values[${index}][id]`"
                        :value="row.id"
                    />

                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p
                            class="text-muted-foreground font-display text-xs font-bold tracking-[0.08em] uppercase"
                        >
                            Value {{ index + 1 }}
                        </p>

                        <div class="flex items-center gap-3">
                            <span
                                v-if="row.variantCount"
                                class="text-muted-foreground text-xs"
                            >
                                Defines {{ row.variantCount }} variant<template
                                    v-if="row.variantCount !== 1"
                                    >s</template
                                >
                            </span>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="values.splice(index, 1)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                Remove
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1.5">
                            <Label :for="`value-${index}-label`">Label</Label>
                            <Input
                                :id="`value-${index}-label`"
                                :name="`values[${index}][label]`"
                                :default-value="row.label"
                                required
                                maxlength="255"
                                placeholder="20 litres"
                            />
                            <InputError
                                :message="errors[`values.${index}.label`]"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`value-${index}-value`">Value</Label>
                            <Input
                                :id="`value-${index}-value`"
                                :name="`values[${index}][value]`"
                                :default-value="row.value"
                                required
                                maxlength="255"
                                placeholder="20l"
                            />
                            <InputError
                                :message="errors[`values.${index}.value`]"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`value-${index}-slug`">Slug</Label>
                            <Input
                                :id="`value-${index}-slug`"
                                :name="`values[${index}][slug]`"
                                :default-value="row.slug"
                                maxlength="255"
                                placeholder="made-from-the-label"
                            />
                            <InputError
                                :message="errors[`values.${index}.slug`]"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`value-${index}-color`">
                                Swatch colour
                            </Label>
                            <Input
                                :id="`value-${index}-color`"
                                :name="`values[${index}][color_code]`"
                                :default-value="row.colorCode ?? undefined"
                                maxlength="7"
                                placeholder="#1B4D3E"
                            />
                            <InputError
                                :message="errors[`values.${index}.color_code`]"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label :for="`value-${index}-sort`">Order</Label>
                            <Input
                                :id="`value-${index}-sort`"
                                :name="`values[${index}][sort_order]`"
                                type="number"
                                min="0"
                                :default-value="row.sortOrder"
                            />
                        </div>

                        <div class="flex items-center gap-2 sm:pt-6">
                            <input
                                :id="`value-${index}-active`"
                                :name="`values[${index}][is_active]`"
                                type="checkbox"
                                value="1"
                                :checked="row.isActive"
                                class="border-input size-4 rounded"
                            />
                            <Label :for="`value-${index}-active`">Active</Label>
                        </div>
                    </div>
                </div>
            </AdminCard>
        </Form>

        <AdminCard v-if="!isNew">
            <AdminCardHeader title="Delete this attribute" :icon="Trash2" />

            <div class="space-y-4 p-5">
                <p class="text-muted-foreground text-sm">
                    Its values go with it. An attribute a product still uses is
                    refused — removing it would unpick every variant built on
                    it.
                </p>

                <Form
                    v-bind="AttributeController.destroy.form(attributeId)"
                    v-slot="{ errors: deleteErrors, processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete attribute
                    </Button>
                    <InputError
                        class="mt-2"
                        :message="deleteErrors.attribute"
                    />
                </Form>
            </div>
        </AdminCard>
    </div>
</template>
