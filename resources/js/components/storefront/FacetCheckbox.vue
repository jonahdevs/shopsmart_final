<script setup lang="ts">
import { useId } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';

/**
 * One tickable facet row: label on the left, the number of products it would
 * leave behind on the right. The count is the whole reason the row is worth
 * more than a plain checkbox, so it is always rendered when the server sent one.
 */
const { count = null } = defineProps<{
    label: string;
    checked: boolean;
    count?: number | null;
}>();

const emit = defineEmits<{ change: [checked: boolean] }>();

const id = useId();
</script>

<template>
    <div class="flex items-center gap-2.5">
        <Checkbox
            :id="id"
            :model-value="checked"
            @update:model-value="(value) => emit('change', value === true)"
        />
        <label
            :for="id"
            class="flex min-w-0 flex-1 cursor-pointer items-baseline gap-2 py-0.5 text-sm leading-5"
        >
            <span class="text-foreground min-w-0 flex-1 truncate">
                {{ label }}
            </span>
            <span
                v-if="count !== null"
                class="text-muted-foreground shrink-0 text-xs tabular-nums"
            >
                {{ count }}
            </span>
        </label>
    </div>
</template>
