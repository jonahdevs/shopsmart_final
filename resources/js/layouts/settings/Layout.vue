<script setup lang="ts">
import SettingsScreen from '@/components/admin/settings/SettingsScreen.vue';
import { useSettingsNav } from '@/components/admin/settings/settingsNav';

/**
 * The staff side of the account settings screens.
 *
 * These pages are the General tab, which is why they wear the same chrome as
 * the store's settings rather than a nav of their own. Both the wryterscript
 * and new-ecommerce builds file a staff member's own account under General, and
 * this used to be a parallel aside listing Profile, Security and Appearance — a
 * second nav holding the same three links, free to drift.
 *
 * The title comes from `settingsNav.ts` so the heading and the highlighted row
 * in the sub-nav cannot disagree. The pages carry their own section headings
 * under it and are unaware of any of this.
 */
const { activeScreen } = useSettingsNav();
</script>

<template>
    <SettingsScreen
        :title="activeScreen?.title ?? 'Account'"
        description="Your own account, separate from the store's settings."
    >
        <!--
          The same rhythm the store settings screens use between their cards.
          The measure is no longer held here: every section on these pages is
          now a card, and a card is its own measure.
        -->
        <section class="flex flex-col gap-6">
            <slot />
        </section>
    </SettingsScreen>
</template>
