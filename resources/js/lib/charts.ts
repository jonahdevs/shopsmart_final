/**
 * Shared ApexCharts configuration.
 *
 * Every chart in the back office is built from `chartBaseOptions()` plus the
 * handful of options that make it the chart it is. That is what stops the
 * dashboard from becoming six charts drawn by six different hands.
 *
 * Colours are read from CSS custom properties at call time rather than being
 * listed here. `--chart-1`…`--chart-6` are the validated series ramp and they
 * differ between light and dark, so a hardcoded hex would be right in one theme
 * and wrong in the other. It also means the palette has exactly one home.
 */

/** How many series colours the token ramp defines. */
const SERIES_SLOTS = 6;

function cssValue(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    return value === '' ? fallback : value;
}

/**
 * The series ramp, in its fixed order.
 *
 * Assigned by position and never cycled: a seventh series does not wrap back
 * around to blue, because two series sharing a colour is worse than one being
 * folded into "Other" server-side — which is why every breakdown on the
 * dashboard is capped in the controller rather than here.
 */
export function chartPalette(): string[] {
    return Array.from({ length: SERIES_SLOTS }, (_, index) =>
        cssValue(`--chart-${index + 1}`, '#0a57eb'),
    );
}

/** Ink for axis labels and legends — never a series colour. */
function mutedInk(): string {
    return cssValue('--muted-foreground', '#667085');
}

function gridLine(): string {
    return cssValue('--border', '#e6eaf0');
}

/**
 * Whether the staff theme is currently dark.
 *
 * Read off the root class rather than from `useAppearance()` so this module
 * stays free of Vue reactivity — {@see AdminChart} rebuilds the chart outright
 * when the theme changes, which is the only time this answer can go stale.
 */
function isDark(): boolean {
    return (
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark')
    );
}

/**
 * Options shared by every chart.
 *
 * The toolbar is off because a download/zoom menu on a dashboard tile is
 * clutter nobody uses, and animations are kept short so a deferred prop landing
 * reads as the chart filling in rather than as a transition.
 */
export function chartBaseOptions(): Record<string, unknown> {
    return {
        chart: {
            fontFamily: 'inherit',
            toolbar: { show: false },
            background: 'transparent',
            animations: { enabled: true, speed: 300 },
            parentHeightOffset: 0,
        },
        colors: chartPalette(),
        dataLabels: { enabled: false },
        grid: {
            borderColor: gridLine(),
            strokeDashArray: 4,
            padding: { left: 8, right: 8 },
        },
        stroke: { curve: 'smooth', width: 2 },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: { size: 5 },
            labels: { colors: mutedInk() },
        },
        tooltip: { theme: isDark() ? 'dark' : 'light' },
        noData: {
            text: 'Nothing recorded in this period',
            style: { color: mutedInk(), fontSize: '13px' },
        },
        xaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: mutedInk(), fontSize: '11px' } },
        },
        yaxis: {
            labels: { style: { colors: mutedInk(), fontSize: '11px' } },
        },
    };
}

/**
 * A money formatter for an axis or a tooltip.
 *
 * The symbol comes from the server, which owns {@see \App\Settings\CurrencySettings}.
 * Values on a chart axis are already in major units — see
 * {@see \App\Data\AdminDashboardTimelineData} — so this only groups them; it
 * never divides by 100, and no chart should.
 */
export function moneyAxisFormatter(symbol: string): (value: number) => string {
    return (value: number): string =>
        `${symbol} ${Math.round(value).toLocaleString()}`;
}

/** Compact form for a crowded axis: 1.2M rather than 1,200,000. */
export function compactMoneyFormatter(
    symbol: string,
): (value: number) => string {
    return (value: number): string => {
        if (Math.abs(value) >= 1_000_000) {
            return `${symbol} ${(value / 1_000_000).toFixed(1)}M`;
        }

        if (Math.abs(value) >= 1_000) {
            return `${symbol} ${Math.round(value / 1_000)}k`;
        }

        return `${symbol} ${Math.round(value)}`;
    };
}

/**
 * A sparkline: the shape of a series with every affordance stripped off.
 *
 * No axes, no grid, no tooltip. It sits under a figure that already states the
 * value, so its job is the trend's silhouette and nothing else — adding a
 * tooltip invites someone to read a number off six pixels.
 */
export function sparklineOptions(colorIndex: number): Record<string, unknown> {
    return {
        chart: {
            type: 'area',
            height: 48,
            sparkline: { enabled: true },
            animations: { enabled: false },
        },
        colors: [chartPalette()[colorIndex] ?? chartPalette()[0]],
        stroke: { curve: 'smooth', width: 1.5 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.4, opacityTo: 0, stops: [0, 100] },
        },
        tooltip: { enabled: false },
    };
}

/**
 * A horizontal bar chart for comparing magnitude across named classes.
 *
 * One hue for every bar, deliberately. Length already encodes the magnitude, so
 * colouring each bar differently would imply the categories carry identity that
 * matters — and it would spend six palette slots saying nothing. Categorical
 * colour is for when the series ARE the subject; here they are just labels.
 *
 * Horizontal rather than vertical because these categories have real names
 * ("Out for delivery", a full product title) and a vertical axis would either
 * clip them or turn them on their side.
 */
export function magnitudeBarOptions(
    labels: string[],
    formatted: string[],
): Record<string, unknown> {
    return {
        chart: { type: 'bar', height: 260 },
        colors: [chartPalette()[0]],
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '62%',
                borderRadius: 4,
                borderRadiusApplication: 'end',
            },
        },
        grid: {
            borderColor: gridLine(),
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        legend: { show: false },
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: mutedInk(), fontSize: '11px' } },
        },
        yaxis: { labels: { style: { colors: mutedInk(), fontSize: '11px' } } },
        tooltip: {
            y: {
                formatter: (
                    _value: number,
                    opts: { dataPointIndex: number },
                ): string => formatted[opts.dataPointIndex] ?? '',
            },
        },
    };
}

/**
 * A single stacked bar showing how a total splits.
 *
 * The part-to-whole form for a handful of long-named categories. A donut would
 * read the share off angle, which people are measurably bad at, and would need
 * the labels outside it anyway.
 */
export function shareBarOptions(formatted: string[]): Record<string, unknown> {
    return {
        chart: { type: 'bar', height: 150, stacked: true, stackType: '100%' },
        plotOptions: {
            bar: { horizontal: true, barHeight: '40%', borderRadius: 4 },
        },
        // A 2px gap of the card surface between segments, so adjacent fills
        // read as separate blocks rather than one band changing colour.
        stroke: { width: 2, colors: [cssValue('--card', '#ffffff')] },
        grid: {
            show: false,
            padding: { left: 0, right: 0, top: -20, bottom: -10 },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'left',
            fontSize: '12px',
            markers: { size: 5 },
            labels: { colors: mutedInk() },
        },
        xaxis: {
            categories: ['Revenue'],
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { show: false } },
        tooltip: {
            y: {
                formatter: (
                    _value: number,
                    opts: { seriesIndex: number },
                ): string => formatted[opts.seriesIndex] ?? '',
            },
        },
    };
}

/**
 * The trading timeline: one measure at a time on one axis.
 *
 * Deliberately never two y-scales. Revenue and order count share an x-axis and
 * nothing else — plotting both against their own axes lets the two lines cross
 * wherever the scales happen to put them, which reads as a relationship that
 * does not exist. The dashboard switches measure instead.
 */
export function timelineAreaOptions(
    labels: string[],
    seriesName: string,
    valueFormatter: (value: number) => string,
): Record<string, unknown> {
    return {
        chart: { type: 'area', height: 300, zoom: { enabled: false } },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 100] },
        },
        legend: { show: false },
        xaxis: {
            categories: labels,
            tickAmount: 8,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                style: { colors: mutedInk(), fontSize: '11px' },
            },
        },
        yaxis: {
            labels: {
                formatter: valueFormatter,
                style: { colors: mutedInk(), fontSize: '11px' },
            },
        },
        tooltip: {
            x: { show: true },
            y: {
                formatter: valueFormatter,
                title: { formatter: (): string => seriesName },
            },
        },
        markers: { size: 0, hover: { size: 5 } },
    };
}
