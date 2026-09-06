<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Check, Search, Star, Trash2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ReviewController from '@/actions/App/Http/Controllers/Admin/ReviewController';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminPagination from '@/components/admin/AdminPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { usePermissions } from '@/composables/usePermissions';
import { formatIsoDate, toBadgeVariant } from '@/lib/utils';
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
            { title: 'Dashboard', href: '/admin' },
            { title: 'Reviews', href: '/admin/reviews' },
        ],
    },
});

/**
 * The filter bar is local state that syncs to the URL, not a form post: a
 * filtered queue has to be a shareable link, and a moderator expects the back
 * button to undo a filter.
 */
const form = ref({
    search: filters.search ?? '',
    status: filters.status ?? '',
    rating: filters.rating === null ? '' : String(filters.rating),
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
 * Only the filters that are actually set. Sending empty strings would put
 * `?status=` on every URL and make two identical views look like different
 * pages to the browser's history.
 */
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
            router.get(adminReviews.url({ query: activeQuery() }), undefined, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true },
);

function hrefForPage(page: number): string {
    return adminReviews.url({ query: activeQuery({ page }) });
}

/** Clicking a sortable heading flips direction when it is already the sort. */
function sortHref(column: string): string {
    const direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';

    return adminReviews.url({ query: activeQuery({ sort: column, direction }) });
}

/**
 * The queue is a card list rather than a table, so the sort controls are plain
 * links: `aria-sort` belongs on a column header and would be invalid on an
 * anchor. The arrow is what tells a reader which way the current sort runs.
 */
function sortArrow(column: string): string {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc' ? ' ↑' : ' ↓';
}
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <Head title="Reviews" />

        <AdminPageHeader
            title="Reviews"
            :description="`${pendingCount} review${pendingCount === 1 ? '' : 's'} waiting for a decision.`"
        />

        <div
            v-if="!reviewsEnabled || autoApprove"
            class="bg-muted/50 rounded-lg border p-4 text-sm"
        >
            <p v-if="!reviewsEnabled">
                Reviews are switched off store-wide, so nothing new will arrive
                here. Everything below is what was already collected.
            </p>
            <p v-else>
                New reviews publish immediately without moderation, so this queue
                only fills when somebody pulls one back. Change that under
                review settings.
            </p>
        </div>

        <Card>
            <CardContent class="pt-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-1.5 xl:col-span-2">
                        <Label for="review-search">Search</Label>
                        <div class="relative">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                id="review-search"
                                v-model="form.search"
                                class="pl-8"
                                placeholder="Author, product or wording"
                                type="search"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="review-status">Status</Label>
                        <NativeSelect id="review-status" v-model="form.status">
                            <option value="">All statuses</option>
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </NativeSelect>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="review-rating">Rating</Label>
                        <NativeSelect id="review-rating" v-model="form.rating">
                            <option value="">Any rating</option>
                            <option
                                v-for="rating in [5, 4, 3, 2, 1]"
                                :key="rating"
                                :value="String(rating)"
                            >
                                {{ rating }} star{{ rating === 1 ? '' : 's' }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-4 pt-6">
                <p
                    v-if="reviews.length === 0"
                    class="text-muted-foreground py-12 text-center text-sm"
                >
                    No reviews match these filters.
                </p>

                <div v-else class="flex items-center gap-4 text-sm">
                    <span class="text-muted-foreground">Sort by</span>
                    <Link
                        :href="sortHref('created_at')"
                        preserve-scroll
                        class="hover:underline"
                    >
                        Date{{ sortArrow('created_at') }}
                    </Link>
                    <Link
                        :href="sortHref('rating')"
                        preserve-scroll
                        class="hover:underline"
                    >
                        Rating{{ sortArrow('rating') }}
                    </Link>
                </div>

                <article
                    v-for="review in reviews"
                    :key="review.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    :variant="
                                        toBadgeVariant(review.statusVariant)
                                    "
                                >
                                    {{ review.statusLabel }}
                                </Badge>
                                <span
                                    class="flex items-center gap-1 text-sm font-medium tabular-nums"
                                >
                                    <Star
                                        class="size-4 fill-current"
                                        aria-hidden="true"
                                    />
                                    {{ review.rating }}/5
                                </span>
                                <Badge
                                    v-if="review.verifiedPurchase"
                                    variant="secondary"
                                >
                                    Verified purchase
                                </Badge>
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
                                        processing || review.status ===
                                        'approved'
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
                                        processing || review.status ===
                                        'rejected'
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
            </CardContent>
        </Card>

        <AdminPagination
            :pagination="pagination"
            :href-for-page="hrefForPage"
        />

        <Dialog
            :open="pendingDeletion !== null"
            @update:open="(open) => (pendingDeletion = open ? pendingDeletion : null)"
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
