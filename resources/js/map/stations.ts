import { Popup, type Map as MapLibreMap } from 'maplibre-gl';
import type { FeatureCollection, Point } from 'geojson';
import { departuresUrl, fetchDepartures, renderDepartures } from './departures';

// E7.1: stations from a street zoom level, under the vehicles, as small white rings that no vehicle marker looks like.

export const STATION_ZOOM = 15;
const SOURCE = 'stations';
const LAYER = 'stations';
const DARK = '#1F2937';
// The same tolerance as for vehicles; a vehicle drawn over a station keeps the click.
const CLICK_TOLERANCE = 6;
// A station found by the search is shown at least at this zoom.
const FOUND_ZOOM = 16;

export interface Station {
    id: string;
    name: string;
    coordinates: [longitude: number, latitude: number];
}

/**
 * What a search compares: accents, case and spacing do not count ("quinconces" finds "Quinconces").
 */
export function normalise(name: string): string {
    return name.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('fr').replace(/\s+/g, ' ').trim();
}

/**
 * The network's stations, fetched once, when first needed: at street level or by the search. A failed fetch is tried
 * again the next time.
 */
export class StationCatalogue {
    private request: Promise<Station[]> | null = null;

    constructor(readonly url: string) {}

    load(): Promise<Station[]> {
        this.request ??= fetch(this.url, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Stations: ${response.status}`);
                }

                return response.json() as Promise<FeatureCollection<Point, { name: string }>>;
            })
            .then((collection) =>
                collection.features.map((feature) => ({
                    id: String(feature.id),
                    name: feature.properties.name,
                    coordinates: feature.geometry.coordinates as [number, number],
                })),
            )
            .catch((error: unknown) => {
                this.request = null;
                throw error;
            });

        return this.request;
    }
}

export interface StationLayer {
    open(station: Station): void;
}

/**
 * Draws the stations under `beforeLayer` once the map reaches a street zoom; their name on hover, in a popup on click
 * with their next departures (E8.2), read again every minute while it stays open. `onToggle` hears which station's
 * popup is open, or that none is.
 */
export function showStations(
    map: MapLibreMap,
    catalogue: StationCatalogue,
    beforeLayer: string,
    onToggle: (station: Station | null) => void,
    timeZone: string,
): StationLayer {
    let drawn = false;
    const hover = new Popup({ closeButton: false, closeOnClick: false, className: 'station-hover', offset: 8 });
    // The focus stays where it was: in the search field when the search opened it, so that a screen reader hears the
    // status rather than a close button. It is closed here rather than by MapLibre's closeOnClick, which closed it again
    // right after a click had opened it on another station.
    const popup = new Popup({ className: 'station-popup', offset: 8, maxWidth: '16rem', focusAfterOpen: false, closeOnClick: false });
    let shown: string | null = null;
    let refreshing: number | undefined;
    popup.on('close', () => {
        shown = null;
        window.clearInterval(refreshing);
        onToggle(null);
    });

    function draw(stations: Station[]): void {
        if (drawn) {
            return;
        }

        drawn = true;
        // For the browser tests, like the vehicle and shape counts.
        map.getContainer().dataset.stationCount = String(stations.length);
        map.addSource(SOURCE, {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: stations.map((station) => ({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: station.coordinates },
                    // MapLibre drops ids that are not numbers: the station's id goes in its properties.
                    properties: { id: station.id, name: station.name },
                })),
            },
        });
        map.addLayer(
            {
                id: LAYER,
                type: 'circle',
                source: SOURCE,
                minzoom: STATION_ZOOM,
                paint: { 'circle-radius': 4, 'circle-color': '#FFFFFF', 'circle-stroke-color': DARK, 'circle-stroke-width': 1.5 },
            },
            map.getLayer(beforeLayer) === undefined ? undefined : beforeLayer,
        );

        map.on('mousemove', LAYER, ({ features }) => {
            const feature = features?.[0];
            if (feature === undefined || feature.geometry.type !== 'Point') {
                return;
            }

            map.getCanvas().style.cursor = 'pointer';
            hover.setLngLat(feature.geometry.coordinates as [number, number]).setText(String(feature.properties.name)).addTo(map);
        });
        map.on('mouseleave', LAYER, () => {
            map.getCanvas().style.cursor = '';
            hover.remove();
        });
        map.on('click', LAYER, ({ point, features }) => {
            const feature = features?.[0];
            const box: [[number, number], [number, number]] = [
                [point.x - CLICK_TOLERANCE, point.y - CLICK_TOLERANCE],
                [point.x + CLICK_TOLERANCE, point.y + CLICK_TOLERANCE],
            ];

            if (feature === undefined || feature.geometry.type !== 'Point' || (map.getLayer(beforeLayer) !== undefined && map.queryRenderedFeatures(box, { layers: [beforeLayer] }).length > 0)) {
                return;
            }

            show({ id: String(feature.properties.id), name: String(feature.properties.name), coordinates: feature.geometry.coordinates as [number, number] });
        });
    }

    function show(station: Station): void {
        hover.remove();
        const name = Object.assign(document.createElement('p'), { className: 'font-semibold', textContent: station.name });
        // A pole lists many departures: the list scrolls, and takes the focus so that the keyboard scrolls it too.
        const departures = Object.assign(document.createElement('div'), { className: 'max-h-[40vh] overflow-y-auto', tabIndex: 0 });
        departures.setAttribute('aria-label', `Prochains passages à ${station.name}`);
        departures.dataset.departures = '';
        departures.append(Object.assign(document.createElement('p'), { className: 'text-sm', textContent: 'Chargement des prochains passages…' }));
        popup.setLngLat(station.coordinates).setDOMContent(wrap(name, departures)).addTo(map);
        shown = station.id;
        onToggle(station);

        const load = (): void => {
            void fetchDepartures(departuresUrl(catalogue.url, station.id)).then((board) => {
                // The popup may have moved to another station meanwhile.
                if (shown === station.id) {
                    renderDepartures(departures, board, new Date(), timeZone);
                }
            });
        };
        window.clearInterval(refreshing);
        load();
        refreshing = window.setInterval(load, 60_000);
    }

    function wrap(...children: HTMLElement[]): HTMLElement {
        const content = document.createElement('div');
        content.append(...children);

        return content;
    }

    map.on('click', ({ point }) => {
        if (map.getLayer(LAYER) === undefined || map.queryRenderedFeatures(point, { layers: [LAYER] }).length === 0) {
            popup.remove();
        }
    });

    function drawWhenClose(): void {
        if (!drawn && map.getZoom() >= STATION_ZOOM) {
            catalogue.load().then(draw, () => undefined);
        }
    }

    map.on('zoomend', drawWhenClose);
    drawWhenClose();

    return {
        open(station: Station): void {
            catalogue.load().then(draw, () => undefined);
            map.easeTo({ center: station.coordinates, zoom: Math.max(map.getZoom(), FOUND_ZOOM) });
            show(station);
        },
    };
}
