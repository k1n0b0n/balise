import { Map, NavigationControl, setWorkerUrl, type LngLatBoundsLike } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { followVehicles } from './live';
import { showVehicles } from './vehicles';

// The map module never imports Livewire or Alpine (ADR-0010): pages mount it and talk to it through events.

export type Bounds = [west: number, south: number, east: number, north: number];

export interface MapOptions {
    styleUrl: string;
    bounds: Bounds | null;
    label: string;
    vehiclesUrl: string;
    refreshSeconds: number;
}

const FRANCE: LngLatBoundsLike = [
    [-5.2, 41.3],
    [9.6, 51.1],
];

const FRENCH = {
    'AttributionControl.ToggleAttribution': 'Afficher ou masquer les crédits',
    'NavigationControl.ResetBearing': 'Réorienter la carte vers le nord',
    'NavigationControl.ZoomIn': 'Zoom avant',
    'NavigationControl.ZoomOut': 'Zoom arrière',
    'Popup.Close': 'Fermer',
};

// MapLibre 6 runs its worker from a separate file; Vite emits it and gives its URL.
setWorkerUrl(workerUrl);

export function mountMap(container: HTMLElement, options: MapOptions): Map {
    const map = new Map({
        container,
        style: options.styleUrl,
        bounds: options.bounds === null ? FRANCE : [options.bounds.slice(0, 2) as [number, number], options.bounds.slice(2) as [number, number]],
        fitBoundsOptions: { padding: 24 },
        locale: { ...FRENCH, 'Map.Title': options.label },
        attributionControl: { compact: true },
    });

    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');

    // Lets pages and browser tests know the basemap actually rendered.
    map.once('load', () => {
        container.dataset.state = 'loaded';
        followVehicles(options.vehiclesUrl, options.refreshSeconds, showVehicles(map));
    });

    return map;
}
