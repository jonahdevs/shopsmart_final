<script setup lang="ts">
import { computed } from 'vue';
import type { BadgeVariants } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';

/**
 * An order's status, in the server's words.
 *
 * Both the wording and the badge variant come off the enum on the server, so
 * nothing here maps a status to a colour — a new status added in PHP shows up
 * correctly without this component knowing it exists. The variant arrives as a
 * plain string, which is the one thing that has to be narrowed back to what the
 * badge accepts.
 */
const { label, variant } = defineProps<{ label: string; variant: string }>();

const badgeVariant = computed(() => variant as BadgeVariants['variant']);
</script>

<template>
    <Badge :variant="badgeVariant">{{ label }}</Badge>
</template>
