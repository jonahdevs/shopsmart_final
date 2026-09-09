<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import { adminTones } from '@/components/admin/tones';
import { Button } from '@/components/ui/button';

/**
 * What the last bulk action actually did, for the rows it did not do it to.
 *
 * A bulk endpoint decides every id on its own, so its answer is rarely one
 * sentence — and a toast reading "18 updated, 4 skipped, 3 refused" is worse
 * than no toast at all if the reader cannot find out which seven. Naming them
 * is the entire job of this strip: the server sends each one's label and its
 * reason, and they are printed as written.
 *
 * Skipped and refused stay visually separate. "Already published" is the system
 * agreeing with you and needs no action; "In the bin — restore it first" is
 * work still outstanding. Merging them into one grey list would bury the second
 * kind inside the first, which is the common one.
 *
 * Dismissible, and gone on the next visit anyway — the server flashes it for a
 * single request, so it never outlives the click that produced it.
 */
const { result } = defineProps<{
    result: App.Data.BulkActionResultData;
}>();

defineEmits<{ dismiss: [] }>();

/**
 * Refusals set the tone even when skips outnumber them: they are the half of
 * the message that still needs somebody to do something.
 */
const tone = computed(() => {
    if (result.refused.length > 0) {
        return 'warning' as const;
    }

    return result.skipped.length > 0 ? 'info' : 'success';
});

const groups = computed(() =>
    [
        { key: 'refused', heading: 'Refused', rows: result.refused },
        { key: 'skipped', heading: 'Skipped', rows: result.skipped },
    ].filter((group) => group.rows.length > 0),
);
</script>

<template>
    <div class="flex items-start gap-3 border-b px-5 py-3">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium" :class="adminTones[tone].text">
                {{ result.summary }}
            </p>

            <div v-if="groups.length > 0" class="mt-2 space-y-2">
                <div v-for="group in groups" :key="group.key">
                    <p
                        class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                    >
                        {{ group.heading }}
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        <li
                            v-for="row in group.rows"
                            :key="row.id"
                            class="text-muted-foreground text-sm"
                        >
                            <span class="text-foreground font-medium">
                                {{ row.label }}
                            </span>
                            — {{ row.reason }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <Button
            variant="ghost"
            size="sm"
            class="shrink-0"
            @click="$emit('dismiss')"
        >
            <X class="size-4" aria-hidden="true" />
            <span class="sr-only">Dismiss this result</span>
        </Button>
    </div>
</template>
