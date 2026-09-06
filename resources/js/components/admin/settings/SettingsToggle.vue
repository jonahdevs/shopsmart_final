<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * One boolean setting.
 *
 * `value="1"` matters: reka-ui's hidden input defaults to `"on"`, which
 * Laravel's `boolean` rule rejects. An unticked box submits nothing at all, so
 * every boolean is validated as `nullable|boolean` and read back with
 * `$request->boolean()` — absent is a deliberate "off".
 */
defineProps<{
    name: string;
    label: string;
    description?: string;
    checked: boolean;
    error?: string;
}>();
</script>

<template>
    <div class="flex items-start gap-3">
        <Checkbox
            :id="name"
            :name="name"
            value="1"
            :default-value="checked"
            class="mt-0.5"
        />

        <div class="grid gap-1">
            <Label :for="name" class="font-medium">{{ label }}</Label>

            <p v-if="description" class="text-muted-foreground text-xs">
                {{ description }}
            </p>

            <InputError :message="error" />
        </div>
    </div>
</template>
