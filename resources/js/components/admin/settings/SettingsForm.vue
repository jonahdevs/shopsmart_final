<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The shell every settings screen shares: page heading, the form itself, and
 * one save button at the end of it.
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
    <div class="flex flex-col gap-8">
        <header class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </header>

        <Form
            v-bind="action"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <slot :errors="errors" />

            <div class="flex items-center justify-end">
                <Button type="submit" :disabled="processing">
                    Save changes
                </Button>
            </div>
        </Form>
    </div>
</template>
