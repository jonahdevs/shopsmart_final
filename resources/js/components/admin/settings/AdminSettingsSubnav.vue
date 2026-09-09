<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useSettingsNav } from '@/components/admin/settings/settingsNav';
import { Card } from '@/components/ui/card';

/**
 * The second level of settings navigation: the screens inside the group the
 * tab strip has selected, as a card of links down the side of the page.
 *
 * A card rather than a bare list, because it is a sibling of the form beside it
 * and has to read as the same kind of object. The active row is the pale blue
 * `--accent` band with the electric foreground, which is the same "you are
 * here" the rail uses, at the weight a second level deserves.
 *
 * It renders nothing on a URL no group claims. A group of one still gets its
 * column, so the page does not reflow as a staff member moves between tabs.
 */
const { activeGroup, isCurrentUrl } = useSettingsNav();
</script>

<template>
    <Card
        v-if="activeGroup && activeGroup.screens.length > 0"
        as="nav"
        class="shrink-0 gap-0.5 p-2.5 lg:w-56"
        :aria-label="`${activeGroup.label} settings`"
    >
        <Link
            v-for="screen in activeGroup.screens"
            :key="screen.title"
            :href="screen.href"
            :aria-current="isCurrentUrl(screen.href) ? 'page' : undefined"
            class="focus-visible:outline-ring flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2"
            :class="
                isCurrentUrl(screen.href)
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            "
        >
            <component
                :is="screen.icon"
                class="size-4 shrink-0"
                aria-hidden="true"
            />
            {{ screen.title }}
        </Link>
    </Card>
</template>
