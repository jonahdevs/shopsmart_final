<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The shell every settings screen shares: the page header, the form itself,
 * and the one save button that belongs to it.
 *
 * The heading is {@see AdminPageHeader} rather than a scale of its own. Seven
 * screens each carrying a slightly smaller H1 than the other thirty-four is the
 * kind of drift nobody reports and everybody feels, and the eyebrow is what
 * tells a staff member which of the settings screens they landed on.
 *
 * The header sits *inside* the `<Form>`, which is why this wrapper renders it
 * instead of the pages: the save button belongs in the header's actions like it
 * does on every other admin form, and it needs the `processing` flag that only
 * the form's slot can hand it.
 *
 * The form is uncontrolled throughout — inputs carry `name` and
 * `:default-value` and nothing is mirrored into a ref — so this wrapper only
 * has to hand the slot the `errors` bag the server sent back.
 */
defineProps<{
    action: RouteFormDefinition<'post'>;
    title: string;
    description?: string;
}>();
</script>

<template>
    <Form
        v-bind="action"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <AdminPageHeader
            eyebrow="Settings"
            :title="title"
            :description="description"
        >
            <template #actions>
                <Button type="submit" :disabled="processing">
                    Save changes
                </Button>
            </template>
        </AdminPageHeader>

        <slot :errors="errors" />
    </Form>
</template>
