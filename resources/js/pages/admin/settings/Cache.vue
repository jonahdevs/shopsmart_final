<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CacheSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/CacheSettingsController';
import AdminCard from '@/components/admin/AdminCard.vue';
import SettingsScreen from '@/components/admin/settings/SettingsScreen.vue';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    cache as cacheRoute,
    index as adminSettings,
} from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Settings', href: adminSettings().url },
            { title: 'Cache', href: cacheRoute().url },
        ],
    },
});

/**
 * Each row is its own form so the button is a real submit carrying one value,
 * rather than a click handler assembling a request in script.
 */
const caches = [
    {
        key: 'application',
        label: 'Application',
        description:
            'Everything the store has memorised: the category rails, the facet counts, the settings groups read on every page. Rebuilds itself on the next request.',
    },
    {
        key: 'config',
        label: 'Configuration',
        description:
            'The compiled config file. Clearing it makes the store read config/ and the environment directly, which is slower but never wrong.',
    },
    {
        key: 'route',
        label: 'Routes',
        description:
            'The compiled route table. Clear this after a deploy that changed routes but did not recache them.',
    },
    {
        key: 'view',
        label: 'Views',
        description:
            'Compiled Blade templates. Only the document shell is Blade here, so this is the least often needed of the four.',
    },
    {
        key: 'all',
        label: 'Everything above',
        description:
            'All four in order. The first page after this will be slower than usual while each rebuilds.',
    },
];
</script>

<template>
    <SettingsScreen
        title="Cache"
        description="Throw away what the store has memorised. Every one of these rebuilds itself, so none of them can leave the shop broken."
    >
        <Head title="Cache settings" />

        <AdminCard>
            <div
                v-for="(cache, index) in caches"
                :key="cache.key"
                class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between sm:gap-6"
                :class="index > 0 ? 'border-t' : ''"
            >
                <div class="min-w-0 space-y-1">
                    <p class="text-sm font-semibold">{{ cache.label }}</p>
                    <p class="text-muted-foreground text-sm">
                        {{ cache.description }}
                    </p>
                </div>

                <Form
                    v-bind="CacheSettingsController.update.form()"
                    v-slot="{ processing }"
                    class="shrink-0"
                >
                    <input type="hidden" name="cache" :value="cache.key" />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                    >
                        Clear
                    </Button>
                </Form>
            </div>
        </AdminCard>
    </SettingsScreen>
</template>
