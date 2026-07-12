// `import type` (not a bare `declare module`) keeps this file a module, so the
// block below *augments* @types/leaflet instead of shadowing it with a fresh
// ambient declaration that only has the members listed here.
import type { Layer } from 'leaflet';

declare module 'leaflet' {
    namespace HeatLayer {
        type HeatLatLngTuple = [number, number, number?];
    }

    interface HeatLayerOptions {
        minOpacity?: number;
        maxZoom?: number;
        max?: number;
        radius?: number;
        blur?: number;
        gradient?: Record<number, string>;
    }

    function heatLayer(latlngs: HeatLayer.HeatLatLngTuple[], options?: HeatLayerOptions): Layer;
}

// leaflet.heat ships no types of its own; this is a side-effect-only import
// (it patches `L.heatLayer` onto the global Leaflet namespace above).
declare module 'leaflet.heat';
