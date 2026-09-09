<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Archive, Download, Trash2 } from '@lucide/vue';
import BackupSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/BackupSettingsController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import SettingsScreen from '@/components/admin/settings/SettingsScreen.vue';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    backup as backupRoute,
    index as adminSettings,
} from '@/routes/admin/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Settings', href: adminSettings().url },
            { title: 'Backup', href: backupRoute().url },
        ],
    },
});

const { backups, disk } = defineProps<{
    backups: {
        path: string;
        name: string;
        size: number;
        created_at: string;
    }[];
    /** The filesystem disk the archives are written to. */
    disk: string;
}>();

const sizeFormatter = new Intl.NumberFormat('en-KE', {
    maximumFractionDigits: 1,
});

const dateFormatter = new Intl.DateTimeFormat('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/** Archives are megabytes at least, so there is no point in a bytes case. */
function formatSize(bytes: number): string {
    const megabytes = bytes / 1024 / 1024;

    return megabytes >= 1024
        ? `${sizeFormatter.format(megabytes / 1024)} GB`
        : `${sizeFormatter.format(megabytes)} MB`;
}

function formatDate(iso: string): string {
    return dateFormatter.format(new Date(iso));
}
</script>

<template>
    <SettingsScreen
        title="Backup"
        :description="`Archives of the database and the application, written to the ${disk} disk.`"
    >
        <Head title="Backup settings" />

        <template #actions>
            <Form
                v-bind="BackupSettingsController.store.form()"
                v-slot="{ processing }"
                class="flex items-center gap-2"
            >
                <Button
                    type="submit"
                    name="database_only"
                    value="1"
                    variant="outline"
                    :disabled="processing"
                >
                    Database only
                </Button>
                <Button type="submit" :disabled="processing">
                    Back up everything
                </Button>
            </Form>
        </template>

        <AdminCard>
            <AdminEmptyState
                v-if="backups.length === 0"
                :icon="Archive"
                title="No backups yet"
                description="Taking one runs in the background. It will appear here when the archive is finished."
            />

            <div
                v-for="(backup, index) in backups"
                v-else
                :key="backup.path"
                class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6"
                :class="index > 0 ? 'border-t' : ''"
            >
                <div class="min-w-0 space-y-1">
                    <p class="truncate text-sm font-semibold">
                        {{ backup.name }}
                    </p>
                    <p class="text-muted-foreground text-sm tabular-nums">
                        {{ formatDate(backup.created_at) }} ·
                        {{ formatSize(backup.size) }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="
                                BackupSettingsController.download.url({
                                    query: { path: backup.path },
                                })
                            "
                        >
                            <Download class="size-4" aria-hidden="true" />
                            Download
                        </Link>
                    </Button>

                    <!--
                      No confirm dialog: an archive is a copy, deleting one
                      loses nothing that is still in the store, and the button
                      is behind `settings.manage` already.
                    -->
                    <Form
                        v-bind="BackupSettingsController.destroy.form()"
                        v-slot="{ processing }"
                    >
                        <input type="hidden" name="path" :value="backup.path" />
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            :disabled="processing"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                            <span class="sr-only"
                                >Delete {{ backup.name }}</span
                            >
                        </Button>
                    </Form>
                </div>
            </div>
        </AdminCard>
    </SettingsScreen>
</template>
