<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import SettingsScreen from '@/components/admin/settings/SettingsScreen.vue';
import { Button } from '@/components/ui/button';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * A settings screen that saves something: {@see SettingsScreen} wrapped in the
 * form, with the one save button that belongs to it.
 *
 * The header sits *inside* the `<Form>`, which is why this wrapper renders the
 * screen rather than the pages doing it: the save button belongs in the
 * header's actions like it does on every other admin form, and it needs the
 * `processing` flag that only the form's slot can hand it.
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
    <Form v-bind="action" v-slot="{ errors, processing }">
        <SettingsScreen :title="title" :description="description">
            <template #actions>
                <Button type="submit" :disabled="processing">
                    Save changes
                </Button>
            </template>

            <slot :errors="errors" />
        </SettingsScreen>
    </Form>
</template>
