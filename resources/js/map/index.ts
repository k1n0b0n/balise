import { Map, NavigationControl, setWorkerUrl, type LngLatBoundsLike } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { displayId, followVehicles, type Vehicle } from './live';
import { showLineOptions } from './line-options';
import { LineCatalogue } from './lines';
import { readSelection, writeSelection } from './selection';
import { fetchShapes, showShapes, type ShapeLayer, type Shapes } from './shapes';
import { showStationSearch } from './station-search';
import { showStations, StationCatalogue, type StationLayer } from './stations';
import { showTextView } from './text-view';
import { showVehicles, type VehicleLayer } from './vehicles';

// The map module never imports Livewire or Alpine (ADR-0010): pages mount it and talk to it through events.

export type Bounds = [west: number, south: number, east: number, north: number];

// The page's controls, each optional: the map works without them.
export interface MapControls {
    textView: HTMLElement | null;
    pause: HTMLButtonElement | null;
    lineFilter: HTMLFormElement | null;
    stationSearch: HTMLFormElement | null;
}

export interface MapOptions {
    styleUrl: string;
    bounds: Bounds | null;
    label: string;
    vehiclesUrl: string;
    linesUrl: string;
    shapesUrl: string;
    stationsUrl: string;
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
    // The style's credits are in English ("Data from OpenStreetMap"); MapLibre rewrites their text, not this element (RGAA 8.7).
    container.querySelector('.maplibregl-ctrl-attrib-inner')?.setAttribute('lang', 'en');

    let vehicles: Vehicle[] = [];
    let layer: VehicleLayer | null = null;
    // E7.1: stations, drawn from street level and found by the search.
    const stations = new StationCatalogue(options.stationsUrl);
    let stationLayerReady: (layer: StationLayer) => void = () => undefined;
    const stationLayer = new Promise<StationLayer>((resolve) => (stationLayerReady = resolve));
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
    const lineSelector = controls.lineFilter?.querySelector<HTMLSelectElement>('select[name="ligne"]') ?? null;
    const modeSelector = controls.lineFilter?.querySelector<HTMLSelectElement>('select[name="mode"]') ?? null;
    const lineClear = controls.lineFilter?.querySelector<HTMLElement>('[data-line-clear]') ?? null;

    const lines = new LineCatalogue(options.linesUrl, options.refreshSeconds, () => {
        redraw();
        shapeLayer?.recolour();
    });
    lines.ensure([]);
    const lineOptions = lineSelector === null ? null : showLineOptions(lineSelector, modeSelector, lines);
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

    if (controls.stationSearch !== null) {
        showStationSearch(controls.stationSearch, stations, stationLayer);
    }

    function redraw(): void {
        if (!lines.settled) {
            return;
        }

        const lineId = selectedLineId();
        layer?.update(vehicles);
        textView?.update(vehicles, lineId);
        lineOptions?.update(vehicles, selection.line);
        highlight();

        // Before the first positions there is nothing to fit or to open.
        if (layer === null || vehicles.length === 0) {
            return;
        }

        if (fitPending) {
            fitPending = false;

            if (lineId !== null) {
                fit(vehicles.filter((vehicle) => vehicle.lineId === lineId));
            }
        }

        if (reopenPending !== null) {
            const vehicle = vehicles.find((candidate) => displayId(candidate) === reopenPending);
            reopenPending = null;

            if (vehicle === undefined) {
                selection = { ...selection, vehicle: null };
                writeSelection(selection);
            } else {
                layer.open(vehicle.id);
            }
        }
    }

    // A line the catalogue does not know (yet) highlights nothing, as on the server.
    function selectedLineId(): string | null {
        return selection.line === null ? null : ([...lines.all()].find((line) => line.shortName === selection.line)?.id ?? null);
    }

    // The selected line, or else the selected vehicle's: the line the user is looking at.
    function highlight(): void {
        const lineId = selectedLineId() ?? lines.get(selectedVehicle?.lineId ?? null)?.id ?? null;
        shapeLayer?.highlight(lineId);
        layer?.highlight(lineId);
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

    // Choosing a vehicle selects its line too, in the selector as in the URL; closing its popup ends both.
    function select(vehicle: Vehicle | null): void {
        selectedVehicle = vehicle;
        follow(vehicle?.id ?? null);
        selection = { line: vehicle === null ? null : (lines.get(vehicle.lineId)?.shortName ?? null), vehicle: vehicle === null ? null : displayId(vehicle) };
        changed();
    }

    // The selection changed: the selector, the URL, the "deselect" link and the drawing follow.
    function changed(): void {
        if (lineSelector !== null) {
            lineSelector.value = selection.line ?? '';
        }
        writeSelection(selection);
        showLineClear();
        redraw();
    }

    // A vehicle still waiting to open, from the URL or the text view, would undo a choice made meanwhile.
    function cancelPendingOpenings(): void {
        reopenPending = null;
        waiting = null;
    }

    // Shown with a selected line only; the server renders it the same way before JavaScript loads.
    function showLineClear(): void {
        const name = lineClear?.querySelector('[data-line-clear-name]');

        if (lineClear !== null && name) {
            lineClear.hidden = selection.line === null;
            name.textContent = selection.line ?? '';
        }
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
        stationLayerReady(showStations(map, stations, 'vehicles'));
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
        // Only on new positions, and for a vehicle followed before them: a vehicle opened by this redraw, from the URL,
        // is being zoomed to, and recentring would cancel that, as it would cancel a fit.
        const following = followed;
        lines.ensure(next.map((vehicle) => vehicle.lineId));
        redraw();
        textView?.updated(new Date());

        const vehicle = following === null || following !== followed ? undefined : next.find((candidate) => candidate.id === following);
        if (vehicle !== undefined) {
            map.easeTo({ center: [vehicle.longitude, vehicle.latitude] });
        }
    });

    // The form works without JavaScript by reloading the page with its button; here a choice applies at once.
    const lineFilter = controls.lineFilter;
    if (lineFilter !== null) {
        const submit = lineFilter.querySelector<HTMLElement>('[data-line-submit]');
        if (submit !== null) {
            submit.hidden = true;
        }

        lineFilter.addEventListener('submit', (event) => event.preventDefault());
        lineFilter.addEventListener('change', (event) => {
            // The mode selector only narrows the list of lines (line-options.ts).
            if (event.target !== lineSelector) {
                return;
            }
            cancelPendingOpenings();
            const value = new FormData(lineFilter).get('ligne');
            const line = typeof value === 'string' && value !== '' ? value : null;
            // A vehicle of another line is no longer selected: closing its popup ends its selection and the following.
            if (selectedVehicle !== null && lines.get(selectedVehicle.lineId)?.shortName !== line) {
                layer?.close();
            }
            selection = { ...selection, line };
            fitPending = line !== null;
            changed();
        });
    }

    // The link works without JavaScript by loading the page without `?ligne=`; here it applies at once. It hides, so
    // the focus goes to the selector rather than to the page.
    lineClear?.addEventListener('click', (event) => {
        event.preventDefault();
        cancelPendingOpenings();
        if (selectedVehicle === null) {
            selection = { line: null, vehicle: null };
            changed();
        } else {
            layer?.close();
        }
        lineSelector?.focus();
    });

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
