<script setup lang="ts">
import { cn } from '@/lib/utils';

/**
 * The surface every admin panel sits on.
 *
 * The back office builds a screen out of one card holding a stack of bordered
 * strips — toolbar, bulk bar, table, pagination — rather than four cards with
 * gaps between them. That reads as a single object you are working inside,
 * and it stops a table's own header from competing with a card's padding for
 * the same 24px of gutter.
 *
 * So the card carries no padding by default. Strips own their own, and a strip
 * separates itself from the one above with `border-t`. Pass `padded` for the
 * simple case of a card that is just a box with something in it.
 *
 * There is no shadow. The page is a sunken grey and the card is white with a
 * 1px rule — that is the whole depth model, and it survives dark mode, where a
 * shadow reads as nothing at all.
 */
const { padded = false } = defineProps<{
    padded?: boolean;
    class?: string;
}>();
</script>

<template>
    <div
        :class="
            cn(
                'bg-card text-card-foreground overflow-hidden rounded-lg border',
                padded && 'p-5',
                $props.class,
            )
        "
    >
        <slot />
    </div>
</template>
