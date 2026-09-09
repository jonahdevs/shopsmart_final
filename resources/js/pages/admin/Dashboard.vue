<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    Boxes,
    ChevronRight,
    ClipboardList,
    CreditCard,
    MonitorSmartphone,
    PackageCheck,
    Receipt,
    ScrollText,
    ShoppingBag,
    Star,
    TriangleAlert,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminCardHeader from '@/components/admin/AdminCardHeader.vue';
import AdminChart from '@/components/admin/AdminChart.vue';
import AdminDateRangePicker from '@/components/admin/AdminDateRangePicker.vue';
import AdminEmptyState from '@/components/admin/AdminEmptyState.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatCard from '@/components/admin/AdminStatCard.vue';
import AdminStatusBadge from '@/components/admin/AdminStatusBadge.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import AdminVisitorsMap from '@/components/admin/AdminVisitorsMap.vue';
import type { AdminTone } from '@/components/admin/tones';
import { adminTones } from '@/components/admin/tones';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppearance } from '@/composables/useAppearance';
import { usePermissions } from '@/composables/usePermissions';
import {
    chartSwatchColor,
    compactMoneyFormatter,
    donutShareOptions,
    magnitudeBarOptions,
    sparklineOptions,
    stackedShareBarOptions,
    timelineAreaOptions,
} from '@/lib/charts';
import { formatIsoDate } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminActivity } from '@/routes/admin/activity';
import {
    index as adminOrders,
    show as adminOrder,
} from '@/routes/admin/orders';
import { index as adminProducts } from '@/routes/admin/products';

const { range, stats, recentOrders, charts, visitors, activity } = defineProps<{
    /** The window every period-sensitive figure below is measured over. */
    range: App.Data.AdminDateRangeData;
    stats: App.Data.AdminDashboardStatsData;
    recentOrders: App.Data.AdminOrderRowData[];
    charts?: App.Data.AdminDashboardChartsData;
    visitors?: App.Data.AdminDashboardVisitorsData;
    /*
      Absent, not empty, for a viewer without `activity.view`. The server never
      assembles these rows for them — see DashboardController — so the panel is
      gated on the permission rather than on the prop being empty, which would
      say "nothing has happened" when the truth is "you may not read this".
    */
    activity?: App.Data.AdminActivityRowData[];
}>();

/*
  No breadcrumbs. The trail's job is to say how you got somewhere and offer the
  way back; on the root of the section it would be a single rung pointing at the
  page you are already reading. AdminLayout renders nothing when the list is
  empty, so declaring none is the whole change.
*/

const { can, canAny } = usePermissions();

/*
  Changing the window is a navigation, not a form post: the URL is the state, so
  a chosen range survives a refresh and can be sent to someone else. A preset
  puts only its key in the query string — never the dates it happens to resolve
  to today — so a link to "last 30 days" still means the last thirty days when
  it is opened next month.

  `replace` because flipping between windows is browsing one page, not visiting
  several, and `preserveState` because the measure and grouping switches on the
  panels below are page-local and should survive the change of window.
*/
function setRange(preset: string, from: string, to: string): void {
    const query =
        preset === 'custom' ? { range: preset, from, to } : { range: preset };

    router.get(adminDashboard.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/*
  Four headline figures, each with its own trend and its own silhouette.

  All four carry a sparkline, including the average order value, which used to
  be the one tile without one. A stretching grid renders the whole row at the
  height of its tallest card, so a single tile missing its floor did not save a
  line — it just left one tile with a strip of nothing where its neighbours had
  a shape.

  The four spark colours are the ramp's 1st, 3rd, 5th and 6th slots so no two
  adjacent tiles share a hue. Nothing is being compared across the tiles, so the
  colour is identity, not magnitude.
*/
const tiles = computed(() => [
    {
        label: 'Revenue',
        value: stats.revenueFormatted,
        change: stats.revenueChangePercent,
        icon: Banknote,
        tone: 'success' as const,
        series: charts?.timeline.revenue ?? [],
        spark: 0,
    },
    {
        label: 'Paid orders',
        value: String(stats.paidOrderCount),
        change: stats.paidOrderChangePercent,
        icon: ShoppingBag,
        tone: 'info' as const,
        series: charts?.timeline.orders ?? [],
        spark: 2,
    },
    {
        label: 'Average order',
        value: stats.averageOrderValueFormatted,
        change: stats.averageOrderValueChangePercent,
        icon: Receipt,
        tone: 'accent' as const,
        series: charts?.timeline.averageOrder ?? [],
        spark: 4,
    },
    {
        label: 'New customers',
        value: String(stats.newCustomerCount),
        change: stats.newCustomerChangePercent,
        icon: UserRound,
        tone: 'brand' as const,
        series: charts?.timeline.customers ?? [],
        spark: 5,
    },
]);

/** The sparkline floor, shallower than a lone stat card's. See the KPI row. */
const SPARK_HEIGHT = 40;

/*
  Each queue counter is a filtered view of a real table, so each one links to
  it. A manager who reads "Low stock: 14" and cannot click through to the
  fourteen products has been told a number and denied the work.

  These are three unrelated counts with three different denominators, so they
  are not a breakdown and must never be drawn as one. But they are also not
  headline figures: they carry no trend and nobody reads them as a period
  result. Giving them the same tall tile as revenue said they were the same
  kind of thing and spent a second full band of the screen saying it, so they
  sit in one card as three linked cells instead.
*/
const queues = computed(() => [
    {
        label: 'Awaiting payment',
        value: stats.awaitingPaymentCount,
        icon: CreditCard,
        tone: 'warning' as const,
        href: adminOrders.url({ query: { payment_status: 'pending' } }),
        permissions: ['orders.view', 'orders.manage'],
    },
    {
        label: 'Awaiting fulfilment',
        value: stats.awaitingFulfilmentCount,
        icon: PackageCheck,
        tone: 'info' as const,
        href: adminOrders.url({ query: { status: 'processing' } }),
        permissions: ['orders.view', 'orders.manage'],
    },
    {
        label: 'Low stock',
        value: stats.lowStockCount,
        icon: TriangleAlert,
        tone: 'danger' as const,
        href: adminProducts.url({ query: { stock_status: 'low_stock' } }),
        permissions: ['products.view', 'products.manage'],
    },
]);

const visibleQueues = computed(() =>
    queues.value.filter((queue) => canAny(...queue.permissions)),
);

/*
  One measure at a time, on one axis.

  Revenue and orders share nothing but an x-axis, and giving each its own y-scale
  — which is what the old store's dashboard did — lets the two lines cross
  wherever the scales happen to place them and invites the reader to see a
  relationship that is an artefact of the axes. Switching measures says the same
  things honestly.
*/
type Measure = 'revenue' | 'orders' | 'customers';

const measure = ref<Measure>('revenue');

const measures: { key: Measure; label: string }[] = [
    { key: 'revenue', label: 'Revenue' },
    { key: 'orders', label: 'Paid orders' },
    { key: 'customers', label: 'New customers' },
];

const timelineSeries = computed(() => {
    if (!charts) {
        return [];
    }

    const values = {
        revenue: charts.timeline.revenue,
        orders: charts.timeline.orders,
        customers: charts.timeline.customers,
    }[measure.value];

    const name =
        measures.find((item) => item.key === measure.value)?.label ?? 'Revenue';

    return [{ name, data: values }];
});

const timelineOptions = computed(() => {
    if (!charts) {
        return {};
    }

    const money = compactMoneyFormatter(charts.timeline.currencySymbol);
    const label =
        measures.find((item) => item.key === measure.value)?.label ?? 'Revenue';

    return timelineAreaOptions(
        charts.timeline.labels,
        label,
        measure.value === 'revenue'
            ? money
            : (value: number): string => String(Math.round(value)),
    );
});

/*
  Products and aisles are the same question asked of two different groupings —
  "what is moving" — and they were drawn as two identical ranked bars sitting
  side by side, which read as one chart accidentally printed twice. One card
  with a switch says outright that they are two cuts of one answer, and it is
  the same control the trading chart already uses, so the page teaches the
  gesture once.
*/
type Catalogue = 'products' | 'aisles';

const catalogue = ref<Catalogue>('products');

const catalogues: { key: Catalogue; label: string }[] = [
    { key: 'products', label: 'Products' },
    { key: 'aisles', label: 'Aisles' },
];

/** Turns a breakdown into the {labels, values, formatted} a bar chart needs. */
function breakdown(slices: App.Data.AdminChartSliceData[]) {
    return {
        labels: slices.map((slice) => slice.label),
        values: slices.map((slice) => slice.value),
        formatted: slices.map((slice) => slice.formatted),
    };
}

const sellerChart = computed(() =>
    breakdown(
        (catalogue.value === 'products'
            ? charts?.topProducts
            : charts?.topCategories) ?? [],
    ),
);

/*
  The caveat is a permanent line rather than a tooltip, because it changes what
  the number means: a product filed in three categories spends its units in all
  three, so the aisle figures deliberately total more than the store sold. Both
  notes are two lines tall so switching cut does not resize the card.
*/
const sellerNote = computed(() =>
    catalogue.value === 'products'
        ? 'Ranked by units shipped in the period. Cancelled orders are excluded.'
        : 'Units count once per category a product sits in, so aisles total more than the store sold.',
);

const sellerEmpty = computed(() =>
    catalogue.value === 'products'
        ? {
              title: 'Nothing sold yet',
              description: 'Best sellers appear here once orders are placed.',
          }
        : {
              title: 'No category sales yet',
              description:
                  'Units sold are counted once per category a product sits in.',
          },
);

/*
  Where the orders are sitting.

  This was a ranked horizontal bar, which answered the wrong question twice
  over: it put the biggest status first, destroying the lifecycle the list
  actually is, and it made the reader take an exact count off an axis when the
  count is the thing they act on. As a ladder the statuses stay in the order an
  order moves through them, each one wears the tint the order tables already
  give it, and the count is printed rather than measured — the meter is only
  there to make "a lot" and "a little" visible at a glance.
*/
const statusRows = computed(() => charts?.ordersByStatus ?? []);

const hasStatusOrders = computed(() =>
    statusRows.value.some((slice) => slice.value > 0),
);

/*
  How the money arrived: the one true part-to-whole on this page, and the only
  panel drawn as a donut. Every shilling belongs to exactly one method and the
  slices sum to the total, which is the question a pie answers well and the
  reason the status ladder next door must never become one.

  The hole is what makes it a donut rather than a pie: it holds the total, the
  figure a reader wants beside the split. The rows under it answer "how much,
  exactly", which Apex's own legend cannot, since it can only print the name.
  Ring and rows read the same server-computed share.
*/
const methodRows = computed(() => charts?.revenueByMethod ?? []);

/** A donut plots one flat list of values against one flat list of labels. */
const methodSeries = computed(() =>
    methodRows.value.map((slice) => slice.value),
);

/** The donut's floor. See the Deferred fallback: the skeleton is tuned to it. */
const METHOD_DONUT_HEIGHT = 170;

/*
  Re-resolved when the theme flips.

  AdminChart rebuilds the chart on a theme change and re-reads the shared base
  options as it does, so everything chartBaseOptions() resolves is fresh. An
  options object built up here is not: nothing in this computed depends on the
  appearance, so the slice stroke would keep the light-mode card colour and
  paint white seams across a dark card. Reading the appearance makes "call
  time" land after the flip, which is all the builder needs.
*/
const { resolvedAppearance } = useAppearance();

const methodOptions = computed(() => {
    void resolvedAppearance.value;

    return donutShareOptions(
        methodRows.value.map((slice) => slice.label),
        methodRows.value.map((slice) => slice.formatted),
        METHOD_DONUT_HEIGHT,
    );
});

/*
  A store that has captured nothing gets the empty state, not a ring: Apex given
  all-zero values draws either nothing at all or six equal slices of a total
  that does not exist.
*/
const hasMethodRevenue = computed(() =>
    methodRows.value.some((slice) => slice.value > 0),
);

/*
  Reputation. Lifetime, not windowed — see the controller.

  A five-bucket star spread is the one breakdown every shopper already knows how
  to read, and they know it as a ladder of meters under an average, not as a bar
  chart with an axis. Drawing it the familiar way costs nothing and means the
  panel needs no explaining.
*/
const ratingRows = computed(() => charts?.ratings ?? []);

/*
  Who came to the shop, split by platform.

  A single stacked bar with its own legend, not a second donut. The ring next
  door is a split of three or four fixed payment methods where the total in the
  hole earns its space; this is a ranked list that can grow a tail, and rank is
  the thing a reader wants from it — "Windows leads, Android is close, the rest
  is noise" is a sentence you read left to right along a bar and have to hunt
  for around a ring. Two part-to-whole panels on one screen must not be the same
  drawing anyway, or neither says why it is there.

  Every figure is server-computed. The bar's segment widths and the legend's
  percentages are the same `share`, so they cannot disagree.
*/
const platformRows = computed(() => visitors?.platforms ?? []);

/** The bar wants one single-value series per segment, not one series of many. */
const platformSeries = computed(() =>
    platformRows.value.map((slice) => ({
        name: slice.label,
        data: [slice.value],
    })),
);

const platformOptions = computed(() =>
    stackedShareBarOptions(platformRows.value.map((slice) => slice.formatted)),
);

const hasVisitors = computed(() => (visitors?.sessionCount ?? 0) > 0);

/*
  Where those visitors were.

  A map, and nothing else will do: "where" is the one question on this page that
  is about places rather than about quantities, and no ranked list of fifty
  country names is read the way a shaded world is glanced at. It replaced a
  desktop-against-mobile ratio, which the platform breakdown beside it already
  answers — Android and iPhone are the hand-helds — so the panel was spending a
  card on a second telling of its neighbour's story.

  The map owns its own colours and its own empty state; see AdminVisitorsMap.
*/
const countryRows = computed(() => visitors?.countries ?? []);

/*
  The audit trail, tinted by what happened. `created` and `deleted` are the two
  events a reader scans for — something appeared, something is gone — so they
  take the affirmative and the alarming tone; everything else is a change, which
  is the ordinary case and gets the ordinary tint.

  Deliberately not `toneForVariant`: that maps a *status* enum's badge variant,
  and an activity event is not a status.
*/
const activityTones: Record<string, AdminTone> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'warning',
};

function activityTone(event: string | null): AdminTone {
    return (event && activityTones[event]) || 'neutral';
}

/** "Order #1042", or the bare type when the row names nothing in particular. */
function activitySubject(entry: App.Data.AdminActivityRowData): string | null {
    if (!entry.subjectType) {
        return null;
    }

    return `${entry.subjectType} ${entry.subjectLabel ?? `#${entry.subjectId}`}`;
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head title="Dashboard" />

        <AdminPageHeader
            title="Dashboard"
            :description="`How the store is trading — ${stats.periodLabel.toLowerCase()}.`"
        >
            <template #actions>
                <AdminDateRangePicker
                    :presets="range.presets"
                    :preset="range.preset"
                    :from="range.start ?? ''"
                    :to="range.end ?? ''"
                    align="end"
                    aria-label="Change the reporting period"
                    @change="setRange"
                />
            </template>
        </AdminPageHeader>

        <!-- Headline figures. -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard
                v-for="tile in tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :change="tile.change"
                :icon="tile.icon"
                :tone="tile.tone"
                :hint="tile.change === null ? stats.periodLabel : undefined"
            >
                <template v-if="tile.series.length > 0" #spark>
                    <AdminChart
                        :options="sparklineOptions(tile.spark, SPARK_HEIGHT)"
                        :series="[{ name: tile.label, data: tile.series }]"
                        :height="SPARK_HEIGHT"
                    />
                </template>
            </AdminStatCard>
        </div>

        <!-- Work waiting to be done. -->
        <AdminCard v-if="visibleQueues.length">
            <div class="grid divide-y sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <Link
                    v-for="queue in visibleQueues"
                    :key="queue.label"
                    :href="queue.href"
                    class="hover:bg-muted/40 flex items-center gap-3 px-5 py-4 transition-colors"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-md"
                        :class="adminTones[queue.tone].chip"
                    >
                        <component
                            :is="queue.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span
                            class="font-display block text-xl leading-6 font-extrabold tabular-nums"
                        >
                            {{ queue.value }}
                        </span>
                        <span
                            class="text-muted-foreground block truncate text-xs"
                        >
                            {{ queue.label }}
                        </span>
                    </span>

                    <ChevronRight
                        class="text-muted-foreground/50 size-4 shrink-0"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <!--
              Said out loud, because this card sits directly under four tiles
              that DO honour the range. A reader who has just chosen "last 7
              days" would otherwise read "Low stock: 14" as fourteen products
              that went low this week, when it is how many are low right now.
            -->
            <p class="text-muted-foreground border-t px-5 py-2 text-xs">
                Work waiting right now — these three are not narrowed by the
                date range.
            </p>
        </AdminCard>

        <Deferred data="charts">
            <!--
              The fallback's heights are the panels' real heights, row by row.
              A skeleton that guesses short makes the whole page jump downwards
              the moment the deferred prop lands.
            -->
            <template #fallback>
                <div class="grid gap-4 lg:grid-cols-3">
                    <Skeleton class="h-[360px] lg:col-span-2" />
                    <Skeleton class="h-[360px]" />
                    <Skeleton class="h-[350px]" />
                    <Skeleton class="h-[350px]" />
                    <Skeleton class="h-[350px]" />
                </div>
            </template>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- The trading line. -->
                <AdminCard class="lg:col-span-2">
                    <AdminCardHeader title="Trading" :icon="ClipboardList">
                        <template #actions>
                            <div
                                class="bg-muted flex items-center gap-0.5 rounded-md p-0.5"
                                role="group"
                                aria-label="Measure"
                            >
                                <button
                                    v-for="item in measures"
                                    :key="item.key"
                                    type="button"
                                    class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        measure === item.key
                                            ? 'bg-card text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    :aria-pressed="measure === item.key"
                                    @click="measure = item.key"
                                >
                                    {{ item.label }}
                                </button>
                            </div>
                        </template>
                    </AdminCardHeader>

                    <div class="p-2">
                        <AdminChart
                            :options="timelineOptions"
                            :series="timelineSeries"
                            :height="300"
                        />
                    </div>
                </AdminCard>

                <!-- How the money arrived: donut over its own legend. -->
                <AdminCard class="flex flex-col">
                    <AdminCardHeader
                        title="Revenue by method"
                        :icon="CreditCard"
                    />

                    <template v-if="hasMethodRevenue">
                        <div class="px-5 pt-4 pb-2">
                            <!--
                              The total is laid over the hole rather than
                              printed into it by Apex, which draws its centre
                              label in its own type and its own resolved
                              colours. Here it is the page's own scale and the
                              foreground token, so it follows the theme with
                              the rest of the card — and it is the server's
                              money string, untouched, like every other figure
                              on this page.
                            -->
                            <div class="relative">
                                <AdminChart
                                    :options="methodOptions"
                                    :series="methodSeries"
                                    :height="METHOD_DONUT_HEIGHT"
                                />

                                <div
                                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-1"
                                >
                                    <span
                                        class="text-muted-foreground text-[10px] font-medium tracking-[0.08em] uppercase"
                                    >
                                        Total
                                    </span>
                                    <span
                                        class="font-display text-sm leading-none font-bold tabular-nums"
                                    >
                                        {{
                                            charts?.revenueByMethodTotalFormatted
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col divide-y border-t">
                            <div
                                v-for="(slice, index) in methodRows"
                                :key="slice.label"
                                class="flex flex-1 items-center gap-3 px-5 py-3"
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-[3px]"
                                    :style="{
                                        backgroundColor: chartSwatchColor(index),
                                    }"
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-xs font-medium"
                                >
                                    {{ slice.label }}
                                </span>
                                <span
                                    class="text-xs font-semibold tabular-nums"
                                >
                                    {{ slice.formatted }}
                                </span>
                                <span
                                    class="text-muted-foreground w-11 text-right text-xs tabular-nums"
                                >
                                    {{ slice.share }}%
                                </span>
                            </div>
                        </div>
                    </template>

                    <AdminEmptyState
                        v-else
                        :icon="CreditCard"
                        title="No payments captured"
                        description="Revenue splits by payment method once orders are paid."
                    />
                </AdminCard>

                <!-- What is selling, by product or by aisle. -->
                <AdminCard class="flex flex-col">
                    <AdminCardHeader title="Best sellers" :icon="Boxes">
                        <template #actions>
                            <div
                                class="bg-muted flex items-center gap-0.5 rounded-md p-0.5"
                                role="group"
                                aria-label="Grouping"
                            >
                                <button
                                    v-for="item in catalogues"
                                    :key="item.key"
                                    type="button"
                                    class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                                    :class="
                                        catalogue === item.key
                                            ? 'bg-card text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    :aria-pressed="catalogue === item.key"
                                    @click="catalogue = item.key"
                                >
                                    {{ item.label }}
                                </button>
                            </div>
                        </template>
                    </AdminCardHeader>

                    <div
                        v-if="sellerChart.values.length"
                        class="flex flex-1 flex-col"
                    >
                        <div class="p-2">
                            <AdminChart
                                :options="
                                    magnitudeBarOptions(
                                        sellerChart.labels,
                                        sellerChart.formatted,
                                        240,
                                    )
                                "
                                :series="[
                                    { name: 'Units', data: sellerChart.values },
                                ]"
                                :height="240"
                            />
                        </div>

                        <p
                            class="text-muted-foreground mt-auto min-h-8 px-5 pb-4 text-xs"
                        >
                            {{ sellerNote }}
                        </p>
                    </div>

                    <AdminEmptyState
                        v-else
                        :icon="Boxes"
                        :title="sellerEmpty.title"
                        :description="sellerEmpty.description"
                    />
                </AdminCard>

                <!-- Where the orders are sitting, in lifecycle order. -->
                <AdminCard class="flex flex-col">
                    <AdminCardHeader
                        title="Orders by status"
                        :icon="ShoppingBag"
                    />

                    <div
                        v-if="hasStatusOrders"
                        class="flex flex-1 flex-col justify-center gap-3 p-5"
                    >
                        <div v-for="slice in statusRows" :key="slice.label">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <AdminStatusBadge
                                    :label="slice.label"
                                    :variant="slice.variant ?? 'outline'"
                                />

                                <span class="text-xs tabular-nums">
                                    <span class="font-semibold">
                                        {{ slice.formatted }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        · {{ slice.share }}%
                                    </span>
                                </span>
                            </div>

                            <div
                                class="bg-muted mt-1.5 h-1.5 overflow-hidden rounded-full"
                            >
                                <div
                                    class="bg-primary h-full rounded-full"
                                    :style="{ width: `${slice.share}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <AdminEmptyState
                        v-else
                        :icon="ShoppingBag"
                        title="No orders in this period"
                        description="Orders placed on the storefront are split by status here."
                    />
                </AdminCard>

                <!-- Reputation: the average, then the spread under it. -->
                <AdminCard class="flex flex-col">
                    <AdminCardHeader title="Review ratings" :icon="Star" />

                    <template v-if="charts && charts.reviewCount > 0">
                        <div class="flex items-baseline gap-2 px-5 pt-5">
                            <span
                                class="font-display text-4xl leading-none font-extrabold tabular-nums"
                            >
                                {{ charts.averageRating }}
                            </span>
                            <span class="text-muted-foreground text-xs">
                                of 5 · {{ charts.reviewCount }} approved
                                reviews, all time
                            </span>
                        </div>

                        <div
                            class="flex flex-1 flex-col justify-center gap-3 p-5"
                        >
                            <div
                                v-for="slice in ratingRows"
                                :key="slice.label"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="text-muted-foreground w-12 shrink-0 text-xs font-semibold tabular-nums"
                                >
                                    {{ slice.label }}
                                </span>

                                <div
                                    class="bg-muted h-1.5 flex-1 overflow-hidden rounded-full"
                                >
                                    <div
                                        class="bg-primary h-full rounded-full"
                                        :style="{ width: `${slice.share}%` }"
                                    />
                                </div>

                                <span
                                    class="w-8 shrink-0 text-right text-xs font-semibold tabular-nums"
                                >
                                    {{ slice.formatted }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <AdminEmptyState
                        v-else
                        :icon="Star"
                        title="No approved reviews"
                        description="Approve reviews in the moderation queue to see the spread."
                    />
                </AdminCard>
            </div>
        </Deferred>

        <!--
          Who came to the shop. Its own deferred prop rather than a seventh
          member of `charts`: these come from a different table, they are
          legitimately empty on a store whose banner offers no analytics
          category, and neither half of the page should wait on the other.
        -->
        <Deferred data="visitors">
            <template #fallback>
                <!-- Both skeletons are the panels' real height — 380px, which
                     AdminVisitorsMap states for itself — or the page jumps
                     downwards the moment the prop lands. -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <Skeleton class="h-[380px]" />
                    <Skeleton class="h-[380px] lg:col-span-2" />
                </div>
            </template>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Platform: a ranked split along one bar, with its own legend. -->
                <AdminCard class="flex h-[380px] flex-col">
                    <AdminCardHeader
                        title="Visitors by platform"
                        :icon="MonitorSmartphone"
                    >
                        <template #actions>
                            <span
                                class="text-muted-foreground text-xs tabular-nums"
                            >
                                {{ visitors?.sessionCount.toLocaleString() }}
                                visits · {{ visitors?.newCount.toLocaleString() }}
                                first-time
                            </span>
                        </template>
                    </AdminCardHeader>

                    <template v-if="hasVisitors">
                        <div class="px-5 pt-4 pb-1">
                            <AdminChart
                                :options="platformOptions"
                                :series="platformSeries"
                                :height="72"
                            />
                        </div>

                        <div
                            class="flex flex-1 flex-col divide-y overflow-y-auto border-t"
                        >
                            <div
                                v-for="(slice, index) in platformRows"
                                :key="slice.label"
                                class="flex flex-1 items-center gap-3 px-5 py-2"
                            >
                                <span
                                    class="size-2.5 shrink-0 rounded-[3px]"
                                    :style="{
                                        backgroundColor: chartSwatchColor(index),
                                    }"
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-xs font-medium"
                                >
                                    {{ slice.label }}
                                </span>
                                <span
                                    class="text-xs font-semibold tabular-nums"
                                >
                                    {{ slice.formatted }}
                                </span>
                                <span
                                    class="text-muted-foreground w-11 text-right text-xs tabular-nums"
                                >
                                    {{ slice.share }}%
                                </span>
                            </div>
                        </div>
                    </template>

                    <AdminEmptyState
                        v-else
                        :icon="MonitorSmartphone"
                        title="No visits recorded"
                        description="Visits are only counted for shoppers who accept analytics on the cookie banner."
                    />
                </AdminCard>

                <!-- Country: a place, so a map. Wider than the panel beside it
                     because a world squeezed into a third of the row is an
                     illustration rather than something you can read a country
                     off. -->
                <AdminVisitorsMap
                    class="lg:col-span-2"
                    :countries="countryRows"
                />
            </div>
        </Deferred>

        <!--
          The two "what just happened" panels share a row. Orders takes two
          thirds because it is a table and its columns need the width; the
          trail is a list of short lines and reads fine in the remaining third.
          Either can be absent on permissions, so whichever survives alone
          spans the row rather than leaving a hole in it.
        -->
        <div class="grid items-start gap-4 lg:grid-cols-3">
            <AdminCard
                v-if="canAny('orders.view', 'orders.manage')"
                :class="can('activity.view') ? 'lg:col-span-2' : 'lg:col-span-3'"
            >
                <AdminCardHeader title="Recent orders" :icon="ShoppingBag">
                    <template #actions>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="adminOrders()">View all</Link>
                        </Button>
                    </template>
                </AdminCardHeader>

                <AdminEmptyState
                    v-if="recentOrders.length === 0"
                    :icon="ShoppingBag"
                    title="No orders yet"
                    description="Orders placed on the storefront land here."
                />

                <!--
                  Wide content scrolls inside its own container so the page body
                  never scrolls sideways on a narrow screen.
                -->
                <div v-else class="overflow-x-auto">
                    <AdminTable>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Placed</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="order in recentOrders" :key="order.id">
                                <TableCell class="font-medium">
                                    <Link
                                        :href="adminOrder(order.orderNumber)"
                                        class="hover:text-primary transition-colors"
                                    >
                                        {{ order.orderNumber }}
                                    </Link>
                                </TableCell>
                                <TableCell class="max-w-48 truncate">
                                    {{ order.customerName }}
                                </TableCell>
                                <TableCell>
                                    <AdminStatusBadge
                                        :label="order.statusLabel"
                                        :variant="order.statusVariant"
                                    />
                                </TableCell>
                                <TableCell>
                                    <AdminStatusBadge
                                        :label="order.paymentStatusLabel"
                                        :variant="order.paymentStatusVariant"
                                    />
                                </TableCell>
                                <TableCell class="font-medium tabular-nums">
                                    {{ order.totalFormatted }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatIsoDate(order.placedAt) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </AdminTable>
                </div>
            </AdminCard>

            <!--
              The audit trail, for whoever may read it. Gated on the permission
              rather than on the prop, because the server sends no rows at all to a
              staff member without it — an empty panel would say the wrong thing.
            -->
            <template v-if="can('activity.view')">
                <Deferred data="activity">
                    <template #fallback>
                        <Skeleton class="h-[320px]" />
                    </template>

                        <AdminCard
                    class="flex h-[320px] flex-col"
                    :class="
                        canAny('orders.view', 'orders.manage')
                            ? ''
                            : 'lg:col-span-3'
                    "
                >
                        <AdminCardHeader title="Recent activity" :icon="ScrollText">
                            <template #actions>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="adminActivity()">View all</Link>
                                </Button>
                            </template>
                        </AdminCardHeader>

                        <div
                            v-if="activity && activity.length > 0"
                            class="flex flex-1 flex-col divide-y overflow-y-auto"
                        >
                            <div
                                v-for="entry in activity"
                                :key="entry.id"
                                class="flex flex-1 items-center gap-3 px-5 py-2"
                            >
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-md"
                                    :class="adminTones[activityTone(entry.event)].chip"
                                >
                                    <ScrollText
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-medium"
                                    >
                                        {{ entry.description }}
                                        <span
                                            v-if="activitySubject(entry)"
                                            class="text-muted-foreground font-normal"
                                        >
                                            · {{ activitySubject(entry) }}
                                        </span>
                                    </span>
                                    <span
                                        class="text-muted-foreground block truncate text-xs"
                                    >
                                        {{ entry.causerName ?? 'System' }} ·
                                        {{ formatIsoDate(entry.createdAt) }}
                                        <!--
                                          Said on the row, not only on the trail
                                          screen: `activity.view` says you may see
                                          that a record moved, not that you may read
                                          it, and a row that silently omits the
                                          values reads as a row with none.
                                        -->
                                        <template
                                            v-if="
                                                entry.valuesHidden &&
                                                entry.changes.length > 0
                                            "
                                        >
                                            · values hidden
                                        </template>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <AdminEmptyState
                            v-else
                            :icon="ScrollText"
                            title="Nothing recorded yet"
                            description="Changes staff make in the admin panel are logged here."
                        />
                    </AdminCard>
                </Deferred>
            </template>
        </div>
    </div>
</template>
