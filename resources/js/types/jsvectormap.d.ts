/**
 * Types for `jsvectormap`, which ships none of its own.
 *
 * Only the surface the visitors map actually uses is described. A fuller
 * transcription of the library's option list would be a second copy of its
 * documentation that nothing here type-checks against, and it would go stale
 * silently — an option this application never passes is better left undeclared,
 * because adding it is then a deliberate act with a compiler error behind it.
 */
declare module 'jsvectormap' {
    export interface JsVectorMapTooltip {
        text(content: string, html?: boolean): void;
    }

    export interface JsVectorMapRegionState {
        fill?: string;
        stroke?: string;
        strokeWidth?: number;
        cursor?: string;
        fillOpacity?: number;
    }

    export interface JsVectorMapFocus {
        region?: string;
        regions?: string[];
        scale?: number;
        animate?: boolean;
    }

    export interface JsVectorMapOptions {
        selector: string | HTMLElement;
        map: string;
        backgroundColor?: string;
        draggable?: boolean;
        zoomButtons?: boolean;
        zoomOnScroll?: boolean;
        zoomMax?: number;
        zoomMin?: number;
        zoomStep?: number;
        zoomAnimate?: boolean;
        showTooltip?: boolean;
        regionsSelectable?: boolean;
        visualizeData?: {
            scale: [string, string];
            values: Record<string, number>;
        };
        regionStyle?: {
            initial?: JsVectorMapRegionState;
            hover?: JsVectorMapRegionState;
            selected?: JsVectorMapRegionState;
        };
        onRegionClick?: (event: MouseEvent, code: string) => void;
        onRegionTooltipShow?: (
            event: Event,
            tooltip: JsVectorMapTooltip,
            code: string,
        ) => void;
    }

    export default class JsVectorMap {
        constructor(options: JsVectorMapOptions);

        setFocus(focus: JsVectorMapFocus): void;

        reset(): void;

        destroy(): void;
    }
}

/**
 * The map data is a side-effect import: it registers itself against the
 * constructor rather than exporting anything, which is why it has to be loaded
 * after the library and why there is nothing here worth naming.
 */
declare module 'jsvectormap/dist/maps/world-merc' {
    const map: unknown;

    export default map;
}
