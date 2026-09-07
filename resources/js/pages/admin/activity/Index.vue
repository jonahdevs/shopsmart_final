<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ScrollText } from '@lucide/vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminSortableHead from '@/components/admin/AdminSortableHead.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useIndexTable } from '@/composables/useIndexTable';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminActivity } from '@/routes/admin/activity';

type ActivityFilters = {
    log_name: string | null;
    event: string | null;
    subject_type: string | null;
    causer_id: number | string | null;
    from: string | null;
    to: string | null;
    sort: string;
    direction: string;
};

const {
    entries,
    pagination,
    filters,
    logNames,
    events,
    subjectTypes,
    causers,
} = defineProps<{
    entries: App.Data.AdminActivityRowData[];
    pagination: App.Data.PaginationData;
    filters: ActivityFilters;
    logNames: string[];
    events: string[];
    subjectTypes: { value: string; label: string }[];
    causers: { value: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Activity', href: adminActivity().url },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * narrowed trail has to be a link an auditor can send. `useIndexTable` owns the
 * debounce, the visit options and the rule that empty filters are omitted.
 */
const { form, isFiltered, hrefForPage, sortHref, ariaSort, clear } =
    useIndexTable({
        toUrl: (query) => adminActivity.url({ query }),
        sortState: () => filters,
        defaultSort: { column: 'created_at', direction: 'desc' },
        fields: {
            log_name: filters.log_name ?? '',
            event: filters.event ?? '',
            subject_type: filters.subject_type ?? '',
            causer_id:
                filters.causer_id === null ? '' : String(filters.causer_id),
            from: filters.from ?? '',
            to: filters.to ?? '',
        },
    });

/** "not set" reads better than an empty cell for a value that was null. */
function shown(value: string | null): string {
    return value ?? 'not set';
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Activity" />

        <AdminPageHeader
            eyebrow="System"
            title="Activity"
            :description="`${pagination.total} recorded ${pagination.total === 1 ? 'event' : 'events'}. Read-only — nothing here can be edited or deleted.`"
        />

        <AdminCard>
            <!--
              No search box: the trail has no free-text search on the server,
              and a box that narrowed nothing would be worse than none.
            -->
            <AdminFilterBar
                :searchable="false"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <NativeSelect
                    v-model="form.log_name"
                    class="w-40"
                    aria-label="Log"
                >
                    <option value="">All logs</option>
                    <option v-for="name in logNames" :key="name" :value="name">
                        {{ name }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.event"
                    class="w-40"
                    aria-label="Event"
                >
                    <option value="">All events</option>
                    <option v-for="event in events" :key="event" :value="event">
                        {{ event }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.subject_type"
                    class="w-40"
                    aria-label="Subject"
                >
                    <option value="">All subjects</option>
                    <option
                        v-for="subject in subjectTypes"
                        :key="subject.value"
                        :value="subject.value"
                    >
                        {{ subject.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.causer_id"
                    class="w-40"
                    aria-label="Who"
                >
                    <option value="">Anyone</option>
                    <option
                        v-for="causer in causers"
                        :key="causer.value"
                        :value="String(causer.value)"
                    >
                        {{ causer.label }}
                    </option>
                </NativeSelect>

                <Input
                    v-model="form.from"
                    type="date"
                    class="w-36"
                    aria-label="Recorded from"
                />
                <Input
                    v-model="form.to"
                    type="date"
                    class="w-36"
                    aria-label="Recorded to"
                />
            </AdminFilterBar>

            <AdminEmptyState
                v-if="entries.length === 0"
                :icon="ScrollText"
                :filtered="isFiltered"
                :title="isFiltered ? 'Nothing recorded' : 'The trail is empty'"
                :description="
                    isFiltered
                        ? 'Nothing has been recorded for these filters.'
                        : 'Staff actions are written here as they happen.'
                "
            />

            <!--
              Wide content scrolls inside its own container so the page body
              never scrolls sideways on a narrow screen.
            -->
            <div v-else class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <AdminSortableHead
                                label="When"
                                :href="sortHref('created_at')"
                                :sort="ariaSort('created_at')"
                            />
                            <TableHead>Who</TableHead>
                            <AdminSortableHead
                                label="What"
                                :href="sortHref('event')"
                                :sort="ariaSort('event')"
                            />
                            <TableHead>Subject</TableHead>
                            <TableHead>Changes</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="entry in entries" :key="entry.id">
                            <TableCell
                                class="text-muted-foreground whitespace-nowrap"
                            >
                                {{ formatIsoDate(entry.createdAt) }}
                            </TableCell>
                            <TableCell>
                                {{ entry.causerName ?? 'System' }}
                            </TableCell>
                            <TableCell>
                                <AdminStatusBadge
                                    :label="entry.event ?? entry.description"
                                    tone="neutral"
                                />
                                <span
                                    class="text-muted-foreground block text-xs"
                                >
                                    {{ entry.logName }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span v-if="entry.subjectType">
                                    {{ entry.subjectType }}
                                    <span class="font-medium">
                                        {{
                                            entry.subjectLabel ??
                                            `#${entry.subjectId}`
                                        }}
                                    </span>
                                </span>
                                <span
                                    v-else
                                    class="text-muted-foreground text-sm"
                                >
                                    —
                                </span>
                            </TableCell>
                            <TableCell class="max-w-md">
                                <p
                                    v-if="entry.changes.length === 0"
                                    class="text-muted-foreground text-sm"
                                >
                                    No attribute changes recorded.
                                </p>

                                <ul v-else class="space-y-0.5 text-sm">
                                    <li
                                        v-for="change in entry.changes"
                                        :key="change.attribute"
                                    >
                                        <span class="text-muted-foreground">
                                            {{ change.label }}:
                                        </span>
                                        <template v-if="entry.valuesHidden">
                                            <span class="text-muted-foreground">
                                                changed
                                            </span>
                                        </template>
                                        <template v-else>
                                            {{ shown(change.from) }}
                                            →
                                            <span class="font-medium">
                                                {{ shown(change.to) }}
                                            </span>
                                        </template>
                                    </li>
                                </ul>

                                <!--
                                  The trail is personal data in its own right:
                                  `activity.view` says you may see that an order
                                  moved, not that you may read the order.
                                -->
                                <p
                                    v-if="
                                        entry.valuesHidden &&
                                        entry.changes.length > 0
                                    "
                                    class="text-muted-foreground pt-1 text-xs"
                                >
                                    Values hidden — you do not have permission
                                    to read this record.
                                </p>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>
    </div>
</template>
