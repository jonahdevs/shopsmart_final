<script setup lang="ts">
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminSettingsSubnav from '@/components/admin/settings/AdminSettingsSubnav.vue';
import AdminSettingsTabs from '@/components/admin/settings/AdminSettingsTabs.vue';

/**
 * The chrome every settings screen wears: the page header, the tab strip of
 * groups, and the sub-nav of screens inside the active group.
 *
 * Separate from {@see SettingsForm} because not every settings screen is a
 * form. Cache and Backup are pages of actions with nothing to save, and they
 * still belong in the tab strip — putting the chrome here is what lets them in
 * without inheriting a Save button they have no use for.
 *
 * Navigation sits between the header and the body rather than above it: over
 * the header it would separate the title from the screen it names. Below `lg`
 * the sub-nav column stacks above the body.
 */
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <AdminPageHeader :title="title" :description="description">
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </AdminPageHeader>

        <AdminSettingsTabs />

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <AdminSettingsSubnav />

            <div class="flex min-w-0 flex-1 flex-col gap-6">
                <slot />
            </div>
        </div>
    </div>
</template>
