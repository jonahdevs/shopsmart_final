<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AttributeController from '@/actions/App/Http/Controllers/Admin/AttributeController';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Attributes', href: '/admin/attributes' },
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
    <div class="flex flex-col gap-6 p-4">
        <Head :title="isNew ? 'New attribute' : attribute.name" />

        <AdminPageHeader
            :title="isNew ? 'New attribute' : attribute.name"
            description="An attribute and its values are saved together."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="adminAttributes()">
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        All attributes
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
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        Leave the slug blank to have one made from the name.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
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

                    <div class="space-y-1.5">
                        <Label for="type">Renders as</Label>
                        <NativeSelect
                            id="type"
                            name="type"
                            :model-value="attribute.type"
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

                    <div class="flex items-center gap-2 sm:col-span-2">
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Values</CardTitle>
                    <CardDescription>
                        A value that still defines a purchasable variant cannot
                        be removed; the save is refused rather than unpicking
                        the variant.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <InputError :message="errors.values" />

                    <p
                        v-if="values.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        This attribute has no values yet.
                    </p>

                    <div
                        v-for="(row, index) in values"
                        :key="index"
                        class="grid gap-4 rounded-md border p-4 sm:grid-cols-4"
                    >
                        <input
                            v-if="row.id !== null"
                            type="hidden"
                            :name="`values[${index}][id]`"
                            :value="row.id"
                        />

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

                        <div
                            class="text-muted-foreground flex items-center text-xs sm:pt-6"
                        >
                            <span v-if="row.variantCount">
                                Defines {{ row.variantCount }} variant<template
                                    v-if="row.variantCount !== 1"
                                    >s</template
                                >
                            </span>
                        </div>

                        <div class="flex items-end justify-end">
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

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addValue"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Add value
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    {{ isNew ? 'Create attribute' : 'Save attribute' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="adminAttributes()">Cancel</Link>
                </Button>
            </div>
        </Form>

        <Card v-if="!isNew">
            <CardHeader>
                <CardTitle>Delete this attribute</CardTitle>
                <CardDescription>
                    Its values go with it. An attribute a product still uses is
                    refused — removing it would unpick every variant built on
                    it.
                </CardDescription>
            </CardHeader>
            <CardContent>
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
                    <InputError class="mt-2" :message="deleteErrors.attribute" />
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
