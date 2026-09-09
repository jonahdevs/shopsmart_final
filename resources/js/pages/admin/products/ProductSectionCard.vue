<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { ref, useId, watch } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';

/**
 * One foldable panel of the product editor.
 *
 * The editor is the longest form in the back office, and the reason it stays
 * readable is that a card opens on whether it has anything to say. A product
 * with no meta title has nothing to show under "Search listing", so that card
 * arrives folded and the create screen is four short panels rather than nine
 * long ones; edit the same product later and the sections you filled in are
 * the ones standing open. The call site decides, because only it can see the
 * content.
 *
 * The body is hidden with `v-show`, never unmounted. Every field on this screen
 * is an uncontrolled `name`d input that the page's one `<Form>` reads out of
 * the DOM at submit time, so a folded card whose fields had been removed would
 * quietly post nothing for them — the fold is a reading aid, and it must not be
 * able to change what is saved.
 *
 * That leaves two ways a staff member could be looking at a folded card with a
 * problem inside it, and both open it:
 *
 * - `invalid`, captured. The browser refuses to submit a form holding an empty
 *   `required` field it cannot show, and says so only in the console. The event
 *   does not bubble, hence the capture phase.
 * - `alerted`, for the errors that come back from the server. A validation
 *   message rendered inside a folded card is a message nobody reads.
 */
const {
    open: startOpen = true,
    alerted = false,
    icon,
    title,
} = defineProps<{
    title: string;
    icon?: LucideIcon;
    /** Whether the card arrives open. Pass a content test, not a constant. */
    open?: boolean;
    /** True while the server is reporting an error about a field in here. */
    alerted?: boolean;
}>();

const isOpen = ref(startOpen);
const bodyId = useId();

watch(
    () => alerted,
    (hasError) => {
        if (hasError) {
            isOpen.value = true;
        }
    },
);
</script>

<template>
    <AdminCard @invalid.capture="isOpen = true">
        <AdminCardHeader
            :title="title"
            :icon="icon"
            collapsible
            :open="isOpen"
            :controls="bodyId"
            @toggle="isOpen = !isOpen"
        >
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </AdminCardHeader>

        <div v-show="isOpen" :id="bodyId">
            <slot />
        </div>
    </AdminCard>
</template>
