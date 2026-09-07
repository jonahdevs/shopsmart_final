<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Percent, Power, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import TaxClassController from '@/actions/App/Http/Controllers/Admin/TaxClassController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminTaxClasses } from '@/routes/admin/tax-classes';

const props = defineProps<{
    taxClass: App.Data.AdminTaxClassFormData;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Tax classes', href: adminTaxClasses().url },
        ],
    },
});

const isNew = computed(() => props.taxClass.id === null);

/** Zero while the tax class does not exist yet — nothing renders it then. */
const taxClassId = computed(() => props.taxClass.id ?? 0);

const submitTarget = computed(() =>
    props.taxClass.id === null
        ? TaxClassController.store.form()
        : TaxClassController.update.form(props.taxClass.id),
);

/**
 * The server refuses both of these deletes. Saying so here turns a refusal a
 * staff member would otherwise only discover by pressing the button into an
 * instruction they can act on — the button stays enabled so the rule is still
 * enforced in one place, on the server.
 */
const deleteBlockedReason = computed(() => {
    if (props.taxClass.productCount > 0) {
        return `${props.taxClass.productCount} product${props.taxClass.productCount === 1 ? ' is' : 's are'} still in this band. Move them to another band first.`;
    }

    if (props.taxClass.isStoreDefault) {
        return 'This is the store’s default band. Choose a different default in shipping and tax settings first.';
    }

    return null;
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="isNew ? 'New tax class' : taxClass.name" />

        <!--
          The page header sits inside the form so Save stays an ordinary submit
          button instead of a detached one wired back by `form=`. The delete
          form below is a sibling, not a child — forms cannot nest.
        -->
        <Form
            v-bind="submitTarget"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <AdminPageHeader
                eyebrow="Catalog"
                :title="isNew ? 'New tax class' : taxClass.name"
                description="Leave the slug blank to have one made from the name."
            >
                <template #actions>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="adminTaxClasses()">Cancel</Link>
                    </Button>
                    <Button type="submit" size="sm" :disabled="processing">
                        {{ isNew ? 'Create tax class' : 'Save tax class' }}
                    </Button>
                </template>
            </AdminPageHeader>

            <!--
              What the band *is* on the left, whether it may still be assigned
              on the right — the two questions are answered at different times,
              so the switch does not sit buried among the copy fields.
            -->
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <AdminCard>
                        <AdminCardHeader title="Details" :icon="Percent" />

                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    :default-value="taxClass.name"
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
                                    :default-value="taxClass.slug ?? undefined"
                                    maxlength="255"
                                    placeholder="made-from-the-name"
                                />
                                <InputError :message="errors.slug" />
                            </div>

                            <!--
                              A percentage, not money. No currency symbol, no
                              minor units — the field holds what the column
                              holds, "16.00" for Kenyan standard VAT.
                            -->
                            <div class="space-y-1.5">
                                <Label for="rate">Rate (%)</Label>
                                <Input
                                    id="rate"
                                    name="rate"
                                    type="number"
                                    inputmode="decimal"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    :default-value="taxClass.rate"
                                    required
                                />
                                <InputError :message="errors.rate" />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    :default-value="
                                        taxClass.description ?? undefined
                                    "
                                    maxlength="5000"
                                />
                                <InputError :message="errors.description" />
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <div class="space-y-6">
                    <AdminCard>
                        <AdminCardHeader title="Availability" :icon="Power" />

                        <div class="space-y-4 p-5">
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    :checked="taxClass.isActive"
                                    class="border-input size-4 rounded"
                                />
                                <Label for="is_active">
                                    Active — may be assigned to products
                                </Label>
                            </div>

                            <p class="text-muted-foreground text-sm">
                                Deactivating a band does not change the products
                                already in it. They keep this rate until they
                                are moved.
                            </p>
                        </div>
                    </AdminCard>
                </div>
            </div>
        </Form>

        <AdminCard v-if="!isNew">
            <AdminCardHeader title="Delete this tax class" :icon="Trash2" />

            <div class="space-y-4 p-5">
                <p class="text-muted-foreground text-sm">
                    {{
                        deleteBlockedReason ??
                        'Nothing is charged at this rate, so removing it changes no price.'
                    }}
                </p>

                <Form
                    v-bind="TaxClassController.destroy.form(taxClassId)"
                    v-slot="{ errors: deleteErrors, processing: deleting }"
                >
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="deleting"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Delete tax class
                    </Button>
                    <InputError class="mt-2" :message="deleteErrors.taxClass" />
                </Form>
            </div>
        </AdminCard>
    </div>
</template>
