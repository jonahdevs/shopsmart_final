<script setup lang="ts">
import { computed } from 'vue';
import type { AdminTone } from '@/components/admin/tones';
import { adminTones, toneForVariant } from '@/components/admin/tones';

/**
 * A status, in the server's words and the server's colour.
 *
 * Nothing here maps a status to a tint. The label and the variant both come off
 * the PHP enum, so a status added in `app/Enums` appears correctly without this
 * component learning it exists — and, more usefully, the storefront's own
 * badge and this one cannot drift into describing the same order differently.
 *
 * Pass `tone` only to override the server, which is worth doing in one case:
 * a column that is *about* a risk (a failed payment in a payments table) may
 * want to shout where the same status inside an order summary should not.
 */
const { variant = 'outline', tone } = defineProps<{
    label: string;
    variant?: string;
    tone?: AdminTone;
}>();

const resolved = computed(() => tone ?? toneForVariant(variant));
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.6875rem] font-bold whitespace-nowrap"
        :class="adminTones[resolved].pill"
    >
        {{ label }}
    </span>
</template>
