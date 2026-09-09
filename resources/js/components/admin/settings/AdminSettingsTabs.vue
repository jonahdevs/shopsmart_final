<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useSettingsNav } from '@/components/admin/settings/settingsNav';

/**
 * The first level of settings navigation: the groups, as a tab strip.
 *
 * Underline tabs rather than another list of links, because a tab strip reads
 * as "these are the same thing seen differently", which is what the groups are.
 * Each tab opens onto its group's first screen, and {@see AdminSettingsSubnav}
 * offers the rest of that group down the side.
 *
 * Only the groups with screens this staff member may reach appear, so the strip
 * grows as the empty ones in `settingsNav.ts` are filled, and a staff member
 * without `settings.manage` — who has General and nothing else — gets no strip
 * at all rather than a strip of one.
 *
 * It scrolls inside itself rather than wrapping, so a narrow screen never
 * widens the document.
 */
const { groups, activeGroup } = useSettingsNav();
</script>

<template>
    <div
        v-if="groups.length > 1"
        class="overflow-x-auto overflow-y-hidden border-b"
    >
        <nav class="-mb-px flex min-w-max gap-1" aria-label="Settings groups">
            <Link
                v-for="group in groups"
                :key="group.key"
                :href="group.screens[0].href"
                :aria-current="
                    activeGroup?.key === group.key ? 'page' : undefined
                "
                class="focus-visible:outline-ring inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2"
                :class="
                    activeGroup?.key === group.key
                        ? 'border-primary text-primary'
                        : 'hover:border-border text-muted-foreground hover:text-foreground border-transparent'
                "
            >
                <component
                    :is="group.icon"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ group.label }}
            </Link>
        </nav>
    </div>
</template>
