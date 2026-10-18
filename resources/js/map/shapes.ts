import type { ExpressionSpecification, GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { FeatureCollection, MultiLineString } from 'geojson';
import type { LineCatalogue } from './lines';

// Every line of the network, faint, under the vehicles (E3.8, ADR-0016): decorative, the text view lists the lines.

const SOURCE = 'shapes';
// A line has several shapes (directions, variants) that overlap: transparent lines would add up where they do. Each
// line is drawn opaque instead, in its colour mixed with Positron's background (#F2F3F0), which looks transparent.
const BACKGROUND = [0xf2, 0xf3, 0xf0];
const STRENGTH = 0.35;

export type Shapes = FeatureCollection<MultiLineString, { line: string }>;

export interface ShapeLayer {
    show(shapes: Shapes): void;
    recolour(): void;
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

    // Colours live in the line catalogue, not in the file: a paint expression, so a new catalogue never rebuilds the tiles.
    function colours(): ExpressionSpecification {
        const byLine = Object.fromEntries([...lines.all()].map((line) => [line.id, faint(line.colour)]));

        return ['to-color', ['coalesce', ['get', ['get', 'line'], ['literal', byLine]], faint(neutralColour)]];
    }

    return {
        show(shapes) {
            map.getSource<GeoJSONSource>(SOURCE)?.setData(shapes);
            map.once('idle', () => (map.getContainer().dataset.shapeCount = String(shapes.features.length)));
        },

        recolour() {
            map.setPaintProperty('shapes', 'line-color', colours());
        },
    };
}

function faint(colour: string): string {
    const channels = [1, 3, 5].map((start, index) => {
        const mixed = STRENGTH * parseInt(colour.slice(start, start + 2), 16) + (1 - STRENGTH) * BACKGROUND[index]!;

        return Math.round(mixed).toString(16).padStart(2, '0');
    });

    return `#${channels.join('')}`;
}
