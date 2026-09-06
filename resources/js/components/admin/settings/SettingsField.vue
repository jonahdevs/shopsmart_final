<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/**
 * Label, control, hint and error for one setting.
 *
 * The control is a slot rather than a prop so the field stays agnostic about
 * which `ui/` primitive renders it, and every one of them stays uncontrolled:
 * the slot hands down the `id` to pair with the label, and the caller binds
 * `name` and `:default-value` itself.
 */
defineProps<{
    name: string;
    label: string;
    hint?: string;
    error?: string;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label :for="name">{{ label }}</Label>

        <slot :id="name" />

        <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>

        <InputError :message="error" />
    </div>
</template>
