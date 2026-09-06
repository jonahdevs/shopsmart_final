<script setup lang="ts">
/**
 * One group of related settings inside a settings form.
 *
 * The screens deliberately hold more than one settings class each, so the
 * section heading is what tells a staff member which part of the store they are
 * editing. Purely presentational — the surrounding `<Form>` owns every input.
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <section class="border-border overflow-hidden rounded-xl border">
        <header class="border-border bg-muted/40 border-b px-6 py-4">
            <h2 class="text-sm font-semibold">{{ title }}</h2>
            <p v-if="description" class="text-muted-foreground mt-1 text-sm">
                {{ description }}
            </p>
        </header>

        <div class="grid gap-6 px-6 py-6">
            <slot />
        </div>
    </section>
</template>
