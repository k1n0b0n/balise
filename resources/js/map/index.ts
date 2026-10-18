import { Map, NavigationControl, setWorkerUrl, type LngLatBoundsLike } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { followVehicles, type Vehicle } from './live';
import { LineCatalogue } from './lines';
import { showTextView } from './text-view';
import { showVehicles, type VehicleLayer } from './vehicles';

// The map module never imports Livewire or Alpine (ADR-0010): pages mount it and talk to it through events.

export type Bounds = [west: number, south: number, east: number, north: number];

export interface MapOptions {
    styleUrl: string;
    bounds: Bounds | null;
    label: string;
    vehiclesUrl: string;
    linesUrl: string;
    neutralColour: string;
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

/**
 * Mounts the map and, when the page has one, its text view; both follow the same live store.
 */
export function mountMap(container: HTMLElement, textViewRoot: HTMLElement | null, options: MapOptions): Map {
    const map = new Map({
        container,
        style: options.styleUrl,
        bounds: options.bounds === null ? FRANCE : [options.bounds.slice(0, 2) as [number, number], options.bounds.slice(2) as [number, number]],
        fitBoundsOptions: { padding: 24 },
        locale: { ...FRENCH, 'Map.Title': options.label },
        attributionControl: { compact: true },
    });

    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');

    let vehicles: Vehicle[] = [];
    let layer: VehicleLayer | null = null;
    // A vehicle chosen in the text view before the basemap is ready opens once it is.
    let waiting: string | null = null;

    const lines = new LineCatalogue(options.linesUrl, options.refreshSeconds, redraw);
    lines.ensure([]);
    const textView =
        textViewRoot === null
            ? null
            : showTextView(textViewRoot, lines, (id) => {
                  if (layer === null) {
                      waiting = id;
                  } else {
                      layer.open(id);
                  }
              });

    function redraw(): void {
        if (!lines.settled) {
            return;
        }

        layer?.update(vehicles);
        textView?.update(vehicles);
    }

    // Lets pages and browser tests know the basemap actually rendered.
    map.once('load', () => {
        container.dataset.state = 'loaded';
        layer = showVehicles(map, lines, options.neutralColour);
        redraw();

        if (waiting !== null) {
            layer.open(waiting);
            waiting = null;
        }
    });

    // Positions and the line catalogue are fetched while the basemap loads, not after.
    followVehicles(options.vehiclesUrl, options.refreshSeconds, (next) => {
        vehicles = next;
        lines.ensure(next.map((vehicle) => vehicle.lineId));
        redraw();
        textView?.updated(new Date());
    });

    return map;
}
