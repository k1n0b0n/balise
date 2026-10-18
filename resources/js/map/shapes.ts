import type { ExpressionSpecification, GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { FeatureCollection, MultiLineString } from 'geojson';
import type { LineCatalogue } from './lines';

// Every line of the network, faint, under the vehicles (E3.8, ADR-0016): decorative, the text view lists the lines.

const SOURCE = 'shapes';
// A line has several shapes (directions, variants) that overlap: transparent lines would add up where they do. Each
// line is drawn opaque instead, in its colour mixed with Positron's background (#F2F3F0), which looks transparent.
const BACKGROUND = [0xf2, 0xf3, 0xf0];
const STRENGTH = 0.35;
// The selected line (E3.4) is drawn again over the others, stronger and wider. A third of TBM's colours stay under 3:1
// on the basemap even at full strength, so a dark casing gives its edges the contrast (WCAG 1.4.11), like the markers'.
const SELECTED_STRENGTH = 0.75;
const CASING = '#1F2937';
const NONE = '';

export type Shapes = FeatureCollection<MultiLineString, { line: string }>;

export interface ShapeLayer {
    show(shapes: Shapes): void;
    recolour(): void;
    highlight(lineId: string | null): void;
}

/**
 * Fetched once, after the first positions: vehicles never wait for it. The server answers `no-cache` with an ETag, so
 * a revisit gets a 304. A failure leaves the map without lines, like before.
 */
export async function fetchShapes(url: string): Promise<Shapes | null> {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(30_000) });

        return response.ok ? ((await response.json()) as Shapes) : null;
    } catch {
        return null;
    }
}

/**
 * Draws the lines in their colours under the basemap labels, so street names stay readable over them.
 */
export function showShapes(map: MapLibreMap, lines: LineCatalogue, neutralColour: string): ShapeLayer {
    map.addSource(SOURCE, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });

    const firstLabels = map.getStyle().layers.find((layer) => layer.type === 'symbol')?.id;

    map.addLayer(
        {
            id: 'shapes',
            type: 'line',
            source: SOURCE,
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
                'line-color': colours(),
                'line-width': ['interpolate', ['linear'], ['zoom'], 10, 1.5, 16, 4],
            },
        },
        firstLabels,
    );
    map.addLayer(
        {
            id: 'shapes-selected-casing',
            type: 'line',
            source: SOURCE,
            filter: ['==', ['get', 'line'], NONE],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': CASING, 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 4, 16, 9] },
        },
        firstLabels,
    );
    map.addLayer(
        {
            id: 'shapes-selected',
            type: 'line',
            source: SOURCE,
            filter: ['==', ['get', 'line'], NONE],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: { 'line-color': colours(SELECTED_STRENGTH), 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 2, 16, 6] },
        },
        firstLabels,
    );

    // Colours live in the line catalogue, not in the file: a paint expression, so a new catalogue never rebuilds the tiles.
    function colours(strength = STRENGTH): ExpressionSpecification {
        const byLine = Object.fromEntries([...lines.all()].map((line) => [line.id, mix(line.colour, strength)]));

        return ['to-color', ['coalesce', ['get', ['get', 'line'], ['literal', byLine]], mix(neutralColour, strength)]];
    }

    return {
        show(shapes) {
            map.getSource<GeoJSONSource>(SOURCE)?.setData(shapes);
            map.once('idle', () => (map.getContainer().dataset.shapeCount = String(shapes.features.length)));
        },

        recolour() {
            map.setPaintProperty('shapes', 'line-color', colours());
            map.setPaintProperty('shapes-selected', 'line-color', colours(SELECTED_STRENGTH));
        },

        highlight(lineId) {
            for (const layer of ['shapes-selected-casing', 'shapes-selected']) {
                map.setFilter(layer, ['==', ['get', 'line'], lineId ?? NONE]);
            }
            map.getContainer().dataset.highlightedLine = lineId ?? '';
        },
    };
}

function mix(colour: string, strength: number): string {
    const channels = [1, 3, 5].map((start, index) => {
        const mixed = strength * parseInt(colour.slice(start, start + 2), 16) + (1 - strength) * BACKGROUND[index]!;

        return Math.round(mixed).toString(16).padStart(2, '0');
    });

    return `#${channels.join('')}`;
}
