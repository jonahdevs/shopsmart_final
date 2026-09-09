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
 * The custom property holding series slot `index`.
 *
 * One function so a slot has exactly one name. Everything that needs the third
 * colour — the ramp handed to Apex, a swatch a hand-built legend paints in HTML
 * — asks here, which is what stops a legend from labelling a slice in a colour
 * the chart never drew it in.
 */
function seriesToken(index: number): string {
    return `--chart-${index + 1}`;
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
        cssValue(seriesToken(index), '#0a57eb'),
    );
}

/**
 * Slot `index` as a colour a stylesheet can use, for a swatch drawn in HTML.
 *
 * A panel that lists its own segments has to paint their swatches itself, and
 * a swatch is only telling the truth if it is the same slot Apex gave the
 * slice — so it comes from here rather than from a second list of class names
 * kept in the page.
 *
 * The token reference is returned unresolved on purpose: a `var()` follows a
 * theme change on its own, with no re-render, which a resolved hex would not.
 * The wrap mirrors what Apex does with a `colors` array shorter than the
 * series, so the two cannot disagree past the end of the ramp either.
 */
export function chartSwatchColor(index: number): string {
    return `var(${seriesToken(index % SERIES_SLOTS)})`;
}

/**
 * How much of the ramp's first slot survives at the quiet end of the map.
 *
 * Low enough to read as a tint rather than as a second series colour, high
 * enough that a country with one visit is still visibly a country with one
 * visit next to `--muted`.
 */
const MAP_TINT_WEIGHT = 0.28;

/** A `#rgb` or `#rrggbb` string as three 0–255 channels, or null if it is neither. */
function hexChannels(color: string): [number, number, number] | null {
    const match =
        /^#([0-9a-f])([0-9a-f])([0-9a-f])$/i.exec(color.trim()) ??
        /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color.trim());

    if (!match) {
        return null;
    }

    const channel = (part: string) =>
        parseInt(part.length === 1 ? part + part : part, 16);

    return [channel(match[1]), channel(match[2]), channel(match[3])];
}

/** `color` laid over `ground` at `weight`, back as `#rrggbb`. */
function mixHex(color: string, ground: string, weight: number): string | null {
    const top = hexChannels(color);
    const bottom = hexChannels(ground);

    if (!top || !bottom) {
        return null;
    }

    return (
        '#' +
        top
            .map((value, index) =>
                Math.round(value * weight + bottom[index] * (1 - weight))
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
}

/**
 * The colours the visitors map paints its regions in.
 *
 * Here rather than in the component for the reason everything else in this file
 * is: the token names are the palette, and a second module reading `--chart-1`
 * would be a second place the ramp can drift from.
 *
 * These come back resolved, unlike {@see chartSwatchColor}, which hands out an
 * unresolved `var()` so a swatch follows a theme change with no re-render. The
 * map cannot take that — it interpolates a fill for every country between the
 * two ends of `shade`, which means parsing them, and `var(--chart-1)` is not a
 * colour to a library that never sees the element. Hex specifically: jsvectormap
 * reads `#rgb` and `#rrggbb` and nothing else, so a token that stopped being hex
 * would silently paint the whole map `NaN`. Hence the validated fallbacks, and
 * hence the caller reading these fresh and rebuilding when the theme flips.
 *
 * `unvisited` is `--muted` and nothing else may be: the panel spends that one
 * grey on "nobody came from there". The quiet end of the ramp is therefore not
 * a grey at all but the ramp's own blue laid thinly over the card — the palette
 * has no step between `--muted` and `--chart-1`, and `--accent` is close enough
 * to `--muted` in both themes that the faintest visited country would read as
 * unvisited. Deriving it from the ramp keeps it a tint of the colour it leads
 * to, in whichever theme is on.
 */
export function visitorMapColors(): {
    shade: [string, string];
    unvisited: string;
    seam: string;
} {
    const strong = hexChannels(cssValue('--chart-1', ''))
        ? cssValue('--chart-1', '#0a57eb')
        : '#0a57eb';

    // Borders are drawn in the card's own colour, so the countries read as
    // tiles cut out of the panel rather than as an outlined atlas.
    const seam = cssValue('--card', '#ffffff');

    return {
        shade: [mixHex(strong, seam, MAP_TINT_WEIGHT) ?? strong, strong],
        unvisited: cssValue('--muted', '#f2f5f9'),
        seam,
    };
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
 *
 * The height is a parameter because the tile's floor is a proportion of the
 * tile, not a fixed band: the dashboard's KPI row is deliberately dense and
 * wants a shallower silhouette than a stat card standing on its own.
 */
export function sparklineOptions(
    colorIndex: number,
    height = 48,
): Record<string, unknown> {
    return {
        chart: {
            type: 'area',
            height,
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
    height = 260,
): Record<string, unknown> {
    return {
        chart: { type: 'bar', height },
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
 * A donut showing how one total divides between a handful of named parts.
 *
 * The form is only honest when every unit of the total belongs to exactly one
 * slice and the slices sum to the whole. Revenue split by payment method is
 * that. A lifecycle is not: drawing order status this way would throw away the
 * order the stages run in and leave cancellations sitting in the denominator,
 * understating every stage an order is actually in.
 *
 * A donut rather than a pie because the hole earns more than the ink it costs
 * — it holds the total, which is the figure a reader wants beside the split.
 * The hole is left empty here and filled by the panel in HTML: Apex's own
 * `total` label cannot reach the project's type scale or its foreground
 * tokens, and a currency string it re-rendered would be one the server did not
 * format. The ring is thin for the same reason, so the hole is wide enough for
 * a full money string.
 *
 * Apex's legend is off, as it is on every panel here: it can only ever print a
 * name, and a part-to-whole panel owes the reader the money and the share too.
 * The panel draws its own rows and paints their swatches with
 * {@see chartSwatchColor}, which is the same ramp Apex is handed below.
 */
export function donutShareOptions(
    labels: string[],
    formatted: string[],
    height = 170,
): Record<string, unknown> {
    return {
        chart: { type: 'donut', height },
        labels,
        plotOptions: {
            pie: {
                // Clicking a slice out of the ring says the slice is selected
                // when nothing on this panel responds to a selection.
                expandOnClick: false,
                donut: { size: '76%', labels: { show: false } },
            },
        },
        // A 2px gap of the card surface between slices, so adjacent fills read
        // as separate blocks rather than one ring changing colour. Resolved
        // here because Apex's default is a literal white, which is a bright
        // seam on a dark card.
        stroke: { width: 2, colors: [cssValue('--card', '#ffffff')] },
        legend: { show: false },
        dataLabels: { enabled: false },
        tooltip: {
            theme: isDark() ? 'dark' : 'light',
            // Off, or Apex paints the tooltip in the slice's own colour and the
            // text on it stops being readable at the light end of the ramp.
            fillSeriesColor: false,
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

/**
 * One horizontal bar showing how a total divides between named parts.
 *
 * The other part-to-whole form on this page is the donut, and the two are not
 * interchangeable. A donut asks the reader to compare angles, which is fine for
 * a split of three or four and stops being fine past that; a single bar asks
 * them to compare lengths along one line, which stays readable as the parts
 * multiply and — unlike a ring — keeps the parts in rank order, so "which
 * platform leads and by how much" is answered by looking left to right.
 *
 * That is why visitors by platform is a bar and revenue by method is a donut:
 * one is a small ranked split that can grow a tail, the other is a handful of
 * fixed methods where the total in the hole is worth the ring around it.
 *
 * Apex's own legend is off for the same reason it is everywhere here — it can
 * only print a name, and a part-to-whole panel owes the reader the count and
 * the share as well. The panel draws its own rows and paints their swatches
 * with {@see chartSwatchColor}, which is the ramp Apex is handed.
 */
export function stackedShareBarOptions(
    formatted: string[],
    height = 72,
): Record<string, unknown> {
    return {
        chart: { type: 'bar', height, stacked: true, stackType: '100%' },
        plotOptions: {
            bar: { horizontal: true, barHeight: '40%', borderRadius: 4 },
        },
        // A 2px gap of the card surface between segments, so adjacent fills
        // read as separate blocks rather than one band changing colour.
        // Resolved here because Apex's default is a literal white, which is a
        // bright seam on a dark card.
        stroke: { width: 2, colors: [cssValue('--card', '#ffffff')] },
        // The bar carries no axis and no legend, so every pixel of the chart's
        // own padding is empty space between the card's gutter and the bar.
        grid: {
            show: false,
            padding: { left: 0, right: 0, top: -20, bottom: -20 },
        },
        legend: { show: false },
        dataLabels: { enabled: false },
        xaxis: {
            categories: ['Share'],
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { show: false } },
        tooltip: {
            theme: isDark() ? 'dark' : 'light',
            fillSeriesColor: false,
            y: {
                formatter: (
                    _value: number,
                    opts: { seriesIndex: number },
                ): string => formatted[opts.seriesIndex] ?? '',
            },
        },
    };
}
