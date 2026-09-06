<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatIsoDate } from '@/lib/utils';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Activity', href: '/admin/activity' },
        ],
    },
});

const form = ref({
    log_name: filters.log_name ?? '',
    event: filters.event ?? '',
    subject_type: filters.subject_type ?? '',
    causer_id: filters.causer_id === null ? '' : String(filters.causer_id),
    from: filters.from ?? '',
    to: filters.to ?? '',
});

function activeQuery(overrides: Record<string, string | number> = {}) {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form.value)) {
        if (value !== '') {
            query[key] = value;
        }
    }

    if (filters.sort !== 'created_at' || filters.direction !== 'desc') {
        query.sort = filters.sort;
        query.direction = filters.direction;
    }

    return { ...query, ...overrides };
}

let debounce: ReturnType<typeof setTimeout> | undefined;

watch(
    form,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            router.get(adminActivity.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminActivity.url({ query: activeQuery({ page }) });
}

function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminActivity.url({ query: activeQuery({ sort: column, direction }) });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (filters.sort !== column) {
        return 'none';
    }

    return filters.direction === 'asc' ? 'ascending' : 'descending';
}

/** "not set" reads better than an empty cell for a value that was null. */
function shown(value: string | null): string {
    return value ?? 'not set';
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Activity" />

        <AdminPageHeader
            title="Activity"
            :description="`${pagination.total} recorded ${pagination.total === 1 ? 'event' : 'events'}. Read-only — nothing here can be edited or deleted.`"
        />

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="space-y-1.5">
                        <Label for="activity-log">Log</Label>
                        <NativeSelect id="activity-log" v-model="form.log_name">
                            <option value="">All logs</option>
                            <option
                                v-for="name in logNames"
                                :key="name"
                                :value="name"
                            >
                                {{ name }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="activity-event">Event</Label>
                        <NativeSelect id="activity-event" v-model="form.event">
                            <option value="">All events</option>
                            <option
                                v-for="event in events"
                                :key="event"
                                :value="event"
                            >
                                {{ event }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="activity-subject">Subject</Label>
                        <NativeSelect
                            id="activity-subject"
                            v-model="form.subject_type"
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
                    </div>

                    <div class="space-y-1.5">
                        <Label for="activity-causer">Who</Label>
                        <NativeSelect
                            id="activity-causer"
                            v-model="form.causer_id"
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
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1.5">
                            <Label for="activity-from">From</Label>
                            <Input
                                id="activity-from"
                                v-model="form.from"
                                type="date"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="activity-to">To</Label>
                            <Input
                                id="activity-to"
                                v-model="form.to"
                                type="date"
                            />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <p
                    v-if="entries.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    Nothing has been recorded for these filters.
                </p>

                <div v-else class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead :aria-sort="ariaSort('created_at')">
                                    <Link
                                        :href="sortHref('created_at')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        When
                                    </Link>
                                </TableHead>
                                <TableHead>Who</TableHead>
                                <TableHead :aria-sort="ariaSort('event')">
                                    <Link
                                        :href="sortHref('event')"
                                        preserve-scroll
                                        class="hover:underline"
                                    >
                                        What
                                    </Link>
                                </TableHead>
                                <TableHead>Subject</TableHead>
                                <TableHead>Changes</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="entry in entries" :key="entry.id">
                                <TableCell class="text-muted-foreground whitespace-nowrap">
                                    {{ formatIsoDate(entry.createdAt) }}
                                </TableCell>
                                <TableCell>
                                    {{ entry.causerName ?? 'System' }}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {{ entry.event ?? entry.description }}
                                    </Badge>
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
                                      The trail is personal data in its own
                                      right: `activity.view` says you may see
                                      that an order moved, not that you may read
                                      the order.
                                    -->
                                    <p
                                        v-if="entry.valuesHidden && entry.changes.length > 0"
                                        class="text-muted-foreground pt-1 text-xs"
                                    >
                                        Values hidden — you do not have
                                        permission to read this record.
                                    </p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <AdminPagination
            :pagination="pagination"
            :href-for-page="hrefForPage"
        />
    </div>
</template>
