<script setup lang="ts">
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';

/**
 * One group of related settings, carried on a card.
 *
 * The store screens deliberately hold more than one settings class each, so the
 * section heading is what tells a staff member which part of the store they are
 * editing. Purely presentational — the surrounding `<Form>` owns every input.
 *
 * Also used by the account screens under `pages/settings/`, which are the
 * General tab and have to read as the same kind of page as the rest of them.
 * Nothing here is admin-only: it is `AdminCard` over semantic tokens, so it
 * carries correctly inside the storefront chrome a customer sees as well.
 *
 * The description leads the body rather than sitting in the title strip:
 * {@see AdminCardHeader} is a one-line label for a region by design, and a
 * paragraph wedged into it would turn every section into a second page heading.
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <AdminCard>
        <AdminCardHeader :title="title" />

        <div class="px-5 py-5">
            <p v-if="description" class="text-muted-foreground mb-5 text-sm">
                {{ description }}
            </p>

            <div class="grid gap-6">
                <slot />
            </div>
        </div>
    </AdminCard>
</template>
