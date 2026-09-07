<script setup lang="ts">
import ApexCharts from 'apexcharts';
import {
    onBeforeUnmount,
    onMounted,
    shallowRef,
    useTemplateRef,
    watch,
} from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { chartBaseOptions } from '@/lib/charts';

/**
 * An ApexCharts instance, wrapped so Vue never fights it for the DOM.
 *
 * Three things here are the whole reason this component exists:
 *
 * The instance lives in a `shallowRef`. ApexCharts holds a large mutable object
 * graph, and letting Vue's reactivity walk it costs a great deal and buys
 * nothing — nothing in the template reads it.
 *
 * The container element is never touched by the template after mount. Apex owns
 * that subtree; re-rendering into it is how you get two charts stacked on top of
 * each other. Updates go through `updateOptions`, which animates from the
 * current series rather than tearing the SVG down and rebuilding it.
 *
 * Theme changes rebuild rather than update. Apex bakes resolved colours into the
 * chart at render, so a token that changed underneath it — which is exactly what
 * switching light to dark does — is not picked up by an options merge.
 */
const { options, series } = defineProps<{
    /** Chart-specific options, merged over the shared base. */
    options: Record<string, unknown>;
    series: unknown;
    /** Height in px. Apex needs a number; a CSS height on the container is ignored. */
    height?: number;
}>();

const container = useTemplateRef<HTMLDivElement>('container');
const chart = shallowRef<ApexCharts | null>(null);
const { resolvedAppearance } = useAppearance();

function resolvedOptions(): Record<string, unknown> {
    return {
        ...chartBaseOptions(),
        ...options,
        chart: {
            ...(chartBaseOptions().chart as Record<string, unknown>),
            ...(options.chart as Record<string, unknown> | undefined),
            height:
                options.height ??
                (options.chart as { height?: number })?.height,
        },
        series,
    };
}

function render(): void {
    if (!container.value) {
        return;
    }

    chart.value?.destroy();
    chart.value = new ApexCharts(container.value, resolvedOptions());
    void chart.value.render();
}

onMounted(render);

onBeforeUnmount(() => {
    chart.value?.destroy();
    chart.value = null;
});

/*
  Data changes animate. A deferred prop landing, or a filter narrowing the
  window, should move the bars rather than blink the chart out and back.
*/
watch(
    () => [options, series],
    () => {
        if (chart.value) {
            void chart.value.updateOptions(
                resolvedOptions(),
                false,
                true,
                true,
            );
        }
    },
    { deep: true },
);

/* A theme change re-resolves every colour, so the chart is rebuilt outright. */
watch(resolvedAppearance, render);
</script>

<template>
    <div
        ref="container"
        :style="height ? { minHeight: `${height}px` } : undefined"
    />
</template>
