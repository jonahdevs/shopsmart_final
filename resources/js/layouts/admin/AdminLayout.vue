<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AdminHeader from '@/layouts/admin/AdminHeader.vue';
import AdminSidebar from '@/layouts/admin/AdminSidebar.vue';
import type { BreadcrumbItem } from '@/types';

/**
 * The staff shell.
 *
 * The back office reads the same tokens as the shop — the sunken canvas, the
 * white cards and the electric blue all come from `:root` in app.css, not from
 * a wrapper class here. That is deliberate: Dialog, Sheet and Popover teleport
 * their content to `document.body`, so anything scoped to a wrapper would be
 * dropped by the first confirm dialog. See the comment above the staff tokens.
 *
 * This layout owns the page's padding. Pages render their own content straight
 * into the slot and must not re-state a page-level `p-*` — that was the source
 * of the seven settings screens sitting flush against the viewport while the
 * other twenty-seven had a gutter.
 *
 * It also owns the breadcrumb trail, which sits at the top of the page body
 * rather than in the header bar. In the bar it was competing with the rail
 * toggle for the same row and had to be hidden below `md`, which took the only
 * wayfinding off a detail page on exactly the screens with the least of it.
 * Above the title it is always there, and it reads as what it is: the path to
 * the heading underneath it.
 */
const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <AppShell variant="sidebar">
            <AdminSidebar />

            <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
                <AdminHeader />

                <div class="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                    <Breadcrumbs
                        v-if="breadcrumbs.length"
                        :breadcrumbs="breadcrumbs"
                        class="-mb-2"
                    />

                    <slot />
                </div>
            </AppContent>

            <Toaster richColors />
        </AppShell>
    </TooltipProvider>
</template>
