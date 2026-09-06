<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Page controls for an admin table.
 *
 * Takes the server's own {@see App.Data.PaginationData} and a builder that
 * turns a page number into a Wayfinder URL, so the current filters travel with
 * the page change — this component never assembles a query string itself.
 *
 * Rendered as Inertia links rather than buttons so a page is a real, shareable
 * URL and the browser's back button behaves.
 */
const { pagination, hrefForPage } = defineProps<{
    pagination: App.Data.PaginationData;
    hrefForPage: (page: number) => string;
}>();

const hasPrevious = computed(() => pagination.currentPage > 1);
const hasNext = computed(() => pagination.currentPage < pagination.lastPage);
</script>

<template>
    <nav
        v-if="pagination.total > 0"
        aria-label="Pagination"
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-muted-foreground text-sm" aria-live="polite">
            Showing {{ pagination.from }}–{{ pagination.to }} of
            {{ pagination.total }}
        </p>

        <div class="flex items-center gap-2">
            <Button
                v-if="hasPrevious"
                variant="outline"
                size="sm"
                as-child
            >
                <Link
                    :href="hrefForPage(pagination.currentPage - 1)"
                    preserve-scroll
                    rel="prev"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                    Previous
                </Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>
                <ChevronLeft class="size-4" aria-hidden="true" />
                Previous
            </Button>

            <span class="text-muted-foreground px-2 text-sm">
                Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
            </span>

            <Button v-if="hasNext" variant="outline" size="sm" as-child>
                <Link
                    :href="hrefForPage(pagination.currentPage + 1)"
                    preserve-scroll
                    rel="next"
                >
                    Next
                    <ChevronRight class="size-4" aria-hidden="true" />
                </Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>
                Next
                <ChevronRight class="size-4" aria-hidden="true" />
            </Button>
        </div>
    </nav>
</template>
