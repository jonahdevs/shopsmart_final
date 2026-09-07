<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, MessageSquare, Star, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ReviewController from '@/actions/App/Http/Controllers/Admin/ReviewController';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { usePermissions } from '@/composables/usePermissions';
import { useIndexTable } from '@/composables/useIndexTable';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as adminCustomer } from '@/routes/admin/customers';
import { index as adminReviews } from '@/routes/admin/reviews';
import { show as shopProduct } from '@/routes/product';

type ReviewFilters = {
    search: string | null;
    status: string | null;
    rating: number | null;
    sort: string;
    direction: string;
};

const {
    reviews,
    pagination,
    filters,
    statusOptions,
    pendingCount,
    autoApprove,
    reviewsEnabled,
} = defineProps<{
    reviews: App.Data.AdminReviewRowData[];
    pagination: App.Data.PaginationData;
    filters: ReviewFilters;
    statusOptions: { value: string; label: string }[];
    pendingCount: number;
    autoApprove: boolean;
    reviewsEnabled: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: adminDashboard().url },
            { title: 'Reviews', href: adminReviews().url },
        ],
    },
});

/** The review a moderator has asked to delete; null while the dialog is shut. */
const pendingDeletion = ref<App.Data.AdminReviewRowData | null>(null);

/**
 * A moderator does not necessarily hold `customers.view`, and the customer page
 * is behind it — so the link to the reviewer's account only appears for someone
 * the server would actually let through.
 */
const { can } = usePermissions();
const canReadCustomers = computed(() => can('customers.view'));

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered queue has to be a shareable link, and a moderator expects the back
 * button to undo a filter. `useIndexTable` owns the debounce, the visit options
 * and the rule that empty filters are omitted rather than sent blank.
 */
const { form, isFiltered, hrefForPage, sortHref, clear } = useIndexTable({
    toUrl: (query) => adminReviews.url({ query }),
    sortState: () => filters,
    defaultSort: { column: 'created_at', direction: 'desc' },
    fields: {
        search: filters.search ?? '',
        status: filters.status ?? '',
        rating: filters.rating === null ? '' : String(filters.rating),
    },
});

/**
 * The queue is a card list rather than a table, so the sort controls are plain
 * links and `AdminSortableHead` does not apply: `aria-sort` belongs on a column
 * header and would be invalid on an anchor. The arrow is what tells a reader
 * which way the current sort runs.
 */
function sortArrow(column: string): string {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc' ? ' ↑' : ' ↓';
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Reviews" />

        <AdminPageHeader
            eyebrow="Marketing"
            title="Reviews"
            :description="`${pendingCount} review${pendingCount === 1 ? '' : 's'} waiting for a decision.`"
        />

        <!--
          Why the queue may look wrong before anyone reads a row: a store-wide
          switch, not a filter, is the reason it is quiet.
        -->
        <AdminCard
            v-if="!reviewsEnabled || autoApprove"
            padded
            class="bg-muted/50 text-sm"
        >
            <p v-if="!reviewsEnabled">
                Reviews are switched off store-wide, so nothing new will arrive
                here. Everything below is what was already collected.
            </p>
            <p v-else>
                New reviews publish immediately without moderation, so this
                queue only fills when somebody pulls one back. Change that under
                review settings.
            </p>
        </AdminCard>

        <!--
          One card, four strips: filters, sort, the queue, pagination. Not four
          cards — they are one object, and the borders between them say so.
        -->
        <AdminCard>
            <AdminFilterBar
                v-model:search="form.search"
                search-placeholder="Author, product or wording"
                search-label="Search reviews"
                :show-clear="isFiltered"
                @clear="clear"
            >
                <NativeSelect
                    v-model="form.status"
                    class="w-40"
                    aria-label="Status"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>

                <NativeSelect
                    v-model="form.rating"
                    class="w-36"
                    aria-label="Rating"
                >
                    <option value="">Any rating</option>
                    <option
                        v-for="rating in [5, 4, 3, 2, 1]"
                        :key="rating"
                        :value="String(rating)"
                    >
                        {{ rating }} star{{ rating === 1 ? '' : 's' }}
                    </option>
                </NativeSelect>
            </AdminFilterBar>

            <AdminEmptyState
                v-if="reviews.length === 0"
                :icon="MessageSquare"
                :filtered="isFiltered"
                :title="isFiltered ? 'No matching reviews' : 'Nothing to read'"
                :description="
                    isFiltered
                        ? 'No reviews match these filters.'
                        : 'Reviews written on the storefront queue up here.'
                "
            />

            <template v-else>
                <div class="flex items-center gap-4 border-b px-5 py-3 text-sm">
                    <span class="text-muted-foreground">Sort by</span>
                    <Link
                        :href="sortHref('created_at')"
                        preserve-scroll
                        class="hover:text-foreground transition-colors"
                    >
                        Date{{ sortArrow('created_at') }}
                    </Link>
                    <Link
                        :href="sortHref('rating')"
                        preserve-scroll
                        class="hover:text-foreground transition-colors"
                    >
                        Rating{{ sortArrow('rating') }}
                    </Link>
                </div>

                <article
                    v-for="review in reviews"
                    :key="review.id"
                    class="border-b px-5 py-4 last:border-b-0"
                >
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <AdminStatusBadge
                                    :label="review.statusLabel"
                                    :variant="review.statusVariant"
                                />
                                <span
                                    class="flex items-center gap-1 text-sm font-medium tabular-nums"
                                >
                                    <Star
                                        class="size-4 fill-current"
                                        aria-hidden="true"
                                    />
                                    {{ review.rating }}/5
                                </span>
                                <AdminStatusBadge
                                    v-if="review.verifiedPurchase"
                                    label="Verified purchase"
                                    tone="info"
                                />
                            </div>

                            <p class="text-sm">
                                <span class="font-medium">
                                    {{ review.authorName }}
                                </span>
                                <!--
                                  A null customerId is ordinary: anonymous and
                                  imported reviews never had an account, and a
                                  reviewer who closed theirs leaves the review
                                  standing under its snapshotted author_name.
                                -->
                                <Link
                                    v-if="review.customerId && canReadCustomers"
                                    :href="adminCustomer(review.customerId)"
                                    class="text-muted-foreground ml-1 underline"
                                >
                                    view account
                                </Link>
                                <span
                                    v-else-if="!review.customerId"
                                    class="text-muted-foreground ml-1"
                                >
                                    (no account)
                                </span>
                                <span class="text-muted-foreground">
                                    · on
                                </span>
                                <Link
                                    v-if="review.productSlug"
                                    :href="shopProduct(review.productSlug)"
                                    class="underline"
                                >
                                    {{ review.productName }}
                                </Link>
                                <span v-else>{{ review.productName }}</span>
                            </p>

                            <p class="text-muted-foreground text-xs">
                                Submitted
                                {{ formatIsoDate(review.submittedAt) }}
                                <template v-if="review.approvedAt">
                                    · approved
                                    {{ formatIsoDate(review.approvedAt) }}
                                </template>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <Form
                                v-bind="ReviewController.update.form(review.id)"
                                :options="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="status"
                                    value="approved"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    :disabled="
                                        processing ||
                                        review.status === 'approved'
                                    "
                                >
                                    <Check class="size-4" aria-hidden="true" />
                                    Approve
                                </Button>
                            </Form>

                            <Form
                                v-bind="ReviewController.update.form(review.id)"
                                :options="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="status"
                                    value="rejected"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    :disabled="
                                        processing ||
                                        review.status === 'rejected'
                                    "
                                >
                                    <X class="size-4" aria-hidden="true" />
                                    Reject
                                </Button>
                            </Form>

                            <Button
                                size="sm"
                                variant="ghost"
                                @click="pendingDeletion = review"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                                <span class="sr-only">
                                    Delete review by {{ review.authorName }}
                                </span>
                            </Button>
                        </div>
                    </div>

                    <p v-if="review.title" class="mt-3 font-medium">
                        {{ review.title }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ review.body }}
                    </p>
                </article>
            </template>

            <AdminPagination
                :pagination="pagination"
                :href-for-page="hrefForPage"
            />
        </AdminCard>

        <Dialog
            :open="pendingDeletion !== null"
            @update:open="
                (open) => (pendingDeletion = open ? pendingDeletion : null)
            "
        >
            <DialogContent v-if="pendingDeletion">
                <DialogHeader>
                    <DialogTitle>Delete this review?</DialogTitle>
                    <DialogDescription>
                        Rejecting keeps the record that somebody looked at it.
                        Deleting removes the review by
                        {{ pendingDeletion.authorName }} for good.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Form
                        v-bind="
                            ReviewController.destroy.form(pendingDeletion.id)
                        "
                        :options="{ preserveScroll: true }"
                        @success="pendingDeletion = null"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Delete review
                        </Button>
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
