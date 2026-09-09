<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import AdminNotificationBell from '@/components/admin/AdminNotificationBell.vue';
import AdminUserMenu from '@/components/admin/AdminUserMenu.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();

/*
  Light → dark → system → light. A three-state cycle rather than a switch,
  because "follow the OS" is a real preference and a two-state toggle silently
  discards it the first time a staff member touches the control.
*/
const cycle: Record<Appearance, Appearance> = {
    light: 'dark',
    dark: 'system',
    system: 'light',
};

/*
  Sun, Moon and Monitor, the same three the wryterscript build uses and the same
  three AppearanceTabs already used on the appearance screen. The header used to
  reach for `Computer` for the system state, which meant one control in the app
  drew "follow the OS" differently from the other.
*/
const appearanceIcon = computed(() => {
    if (appearance.value === 'light') {
        return Sun;
    }

    return appearance.value === 'dark' ? Moon : Monitor;
});

const LABELS: Record<Appearance, string> = {
    light: 'Light',
    dark: 'Dark',
    system: 'System',
};

const appearanceLabel = computed(() => LABELS[appearance.value]);

/** Named in the accessible label, so the control says what it will do. */
const nextAppearanceLabel = computed(() => LABELS[cycle[appearance.value]]);
</script>

<template>
    <header
        class="bg-card sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b px-4 sm:px-6 lg:px-8"
    >
        <SidebarTrigger class="-ml-1" />

        <div class="ml-auto flex items-center gap-1">
            <AdminNotificationBell />

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="updateAppearance(cycle[appearance])"
                    >
                        <component
                            :is="appearanceIcon"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <span class="sr-only">
                            Theme: {{ appearanceLabel }}. Switch to
                            {{ nextAppearanceLabel }}.
                        </span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Theme: {{ appearanceLabel }}</TooltipContent>
            </Tooltip>

            <AdminUserMenu class="ml-1" />
        </div>
    </header>
</template>
