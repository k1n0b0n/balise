import { Map, NavigationControl, setWorkerUrl, type LngLatBoundsLike } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { displayId, followVehicles, type Vehicle } from './live';
import { LineCatalogue } from './lines';
import { readSelection, writeSelection } from './selection';
import { fetchShapes, showShapes, type ShapeLayer, type Shapes } from './shapes';
import { showTextView } from './text-view';
import { showVehicles, type VehicleLayer } from './vehicles';

// The map module never imports Livewire or Alpine (ADR-0010): pages mount it and talk to it through events.

export type Bounds = [west: number, south: number, east: number, north: number];

// The page's controls, each optional: the map works without them.
export interface MapControls {
    textView: HTMLElement | null;
    pause: HTMLButtonElement | null;
    lineFilter: HTMLFormElement | null;
}

export interface MapOptions {
    styleUrl: string;
    bounds: Bounds | null;
    label: string;
    vehiclesUrl: string;
    linesUrl: string;
    shapesUrl: string;
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

// A selected line is fitted at most to this zoom: a single vehicle would otherwise fill the screen.
const FIT_MAX_ZOOM = 15;

// MapLibre 6 runs its worker from a separate file; Vite emits it and gives its URL.
setWorkerUrl(workerUrl);

/**
 * Mounts the map and, when the page has one, its text view; both follow the same live store.
 */
export function mountMap(container: HTMLElement, controls: MapControls, options: MapOptions): Map {
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
    let shapeLayer: ShapeLayer | null = null;
    let shapes: Shapes | null = null;
    let shapesRequested = false;
    // A vehicle chosen in the text view before the basemap is ready opens once it is.
    let waiting: string | null = null;

    // E3.4: the selection from the URL is applied once the first positions are in.
    let selection = readSelection();
    let fitPending = selection.line !== null;
    let reopenPending = selection.vehicle;
    let selectedVehicle: Vehicle | null = null;
    // The vehicle the map keeps centred on; moving the map by hand stops it, the popup stays open.
    let followed: string | null = null;

    const lines = new LineCatalogue(options.linesUrl, options.refreshSeconds, () => {
        redraw();
        shapeLayer?.recolour();
    });
    lines.ensure([]);
    const textView =
        controls.textView === null
            ? null
            : showTextView(controls.textView, lines, (id) => {
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

        const lineId = selectedLineId();
        const shown = lineId === null ? vehicles : vehicles.filter((vehicle) => vehicle.lineId === lineId);
        layer?.update(shown);
        textView?.update(shown);
        highlight();

        // Before the first positions there is nothing to fit or to open.
        if (layer === null || vehicles.length === 0) {
            return;
        }

        if (fitPending) {
            fitPending = false;
            fit(shown);
        }

        if (reopenPending !== null) {
            const vehicle = shown.find((candidate) => displayId(candidate) === reopenPending);
            reopenPending = null;

            if (vehicle === undefined) {
                selection = { ...selection, vehicle: null };
                writeSelection(selection);
            } else {
                layer.open(vehicle.id);
            }
        }

        const vehicle = followed === null ? undefined : shown.find((candidate) => candidate.id === followed);
        if (vehicle !== undefined) {
            map.easeTo({ center: [vehicle.longitude, vehicle.latitude] });
        }
    }

    // A line the catalogue does not know (yet) filters nothing, as on the server.
    function selectedLineId(): string | null {
        return selection.line === null ? null : ([...lines.all()].find((line) => line.shortName === selection.line)?.id ?? null);
    }

    function highlight(): void {
        shapeLayer?.highlight(selectedLineId() ?? lines.get(selectedVehicle?.lineId ?? null)?.id ?? null);
    }

    function fit(shown: Vehicle[]): void {
        if (shown.length === 0) {
            return;
        }

        const longitudes = shown.map((vehicle) => vehicle.longitude);
        const latitudes = shown.map((vehicle) => vehicle.latitude);
        map.fitBounds(
            [
                [Math.min(...longitudes), Math.min(...latitudes)],
                [Math.max(...longitudes), Math.max(...latitudes)],
            ],
            { padding: 48, maxZoom: FIT_MAX_ZOOM },
        );
    }

    function select(vehicle: Vehicle | null): void {
        selectedVehicle = vehicle;
        follow(vehicle?.id ?? null);
        selection = { ...selection, vehicle: vehicle === null ? null : displayId(vehicle) };
        writeSelection(selection);
        highlight();
    }

    function follow(id: string | null): void {
        followed = id;
        // Lets browser tests know which vehicle the map follows.
        container.dataset.followed = id ?? '';
    }

    // Dragging or panning with the arrow keys stops following; zooming keeps the vehicle in the centre.
    map.on('dragstart', () => follow(null));
    map.on('movestart', ({ originalEvent }) => {
        if (originalEvent instanceof KeyboardEvent && originalEvent.key.startsWith('Arrow')) {
            follow(null);
        }
    });

    // Lets pages and browser tests know the basemap actually rendered.
    map.once('load', () => {
        container.dataset.state = 'loaded';
        shapeLayer = showShapes(map, lines, options.neutralColour);
        layer = showVehicles(map, lines, options.neutralColour, select);
        redraw();

        if (shapes !== null) {
            shapeLayer.show(shapes);
        }

        if (waiting !== null) {
            layer.open(waiting);
            waiting = null;
        }
    });

    // Positions and the line catalogue are fetched while the basemap loads, not after.
    const live = followVehicles(options.vehiclesUrl, options.refreshSeconds, (next) => {
        vehicles = next;

        if (!shapesRequested) {
            shapesRequested = true;
            void fetchShapes(options.shapesUrl).then((loaded) => {
                shapes = loaded;

                if (shapes !== null) {
                    shapeLayer?.show(shapes);
                }
            });
        }
        lines.ensure(next.map((vehicle) => vehicle.lineId));
        redraw();
        textView?.updated(new Date());
    });

    // The form works without JavaScript by reloading the page; here it applies at once.
    const lineFilter = controls.lineFilter;
    if (lineFilter !== null) {
        lineFilter.addEventListener('submit', (event) => {
            event.preventDefault();
            const value = new FormData(lineFilter).get('ligne');
            selection = { ...selection, line: typeof value === 'string' && value !== '' ? value : null };
            fitPending = selection.line !== null;
            writeSelection(selection);
            redraw();
        });
    }

    // Rendered hidden: without JavaScript nothing updates, so there is nothing to pause (E3.6, RGAA 13.8).
    const pauseButton = controls.pause;
    if (pauseButton !== null) {
        pauseButton.hidden = false;
        pauseButton.addEventListener('click', () => {
            const pausing = pauseButton.getAttribute('aria-pressed') !== 'true';
            pauseButton.setAttribute('aria-pressed', String(pausing));

            if (pausing) {
                live.pause();
                lines.pause();
                textView?.paused();
            } else {
                live.resume();
                lines.resume();
                textView?.resumed();
            }
        });
    }

    return map;
}
