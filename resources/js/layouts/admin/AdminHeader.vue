<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Computer, ExternalLink, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import { home } from '@/routes';
import type { Appearance, BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

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

const appearanceIcon = computed(() => {
    if (appearance.value === 'light') {
        return Sun;
    }

    return appearance.value === 'dark' ? Moon : Computer;
});

const appearanceLabel = computed(
    () =>
        ({
            light: 'Light',
            dark: 'Dark',
            system: 'System',
        })[appearance.value],
);
</script>

<template>
    <header
        class="bg-card sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b px-4 sm:px-6 lg:px-8"
    >
        <SidebarTrigger class="-ml-1" />

        <!--
          The trail is the only wayfinding on a detail page, but it is also the
          first thing worth losing on a narrow screen — the page's own H1 says
          where you are, and the rail says how to leave.
        -->
        <div class="hidden min-w-0 md:block">
            <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
        </div>

        <div class="ml-auto flex items-center gap-1">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button variant="ghost" size="icon" as-child>
                        <a :href="home().url" target="_blank" rel="noopener">
                            <ExternalLink class="size-4" aria-hidden="true" />
                            <span class="sr-only">View storefront</span>
                        </a>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>View storefront</TooltipContent>
            </Tooltip>

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
                            Change theme, currently {{ appearanceLabel }}
                        </span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Theme: {{ appearanceLabel }}</TooltipContent>
            </Tooltip>
        </div>
    </header>
</template>
