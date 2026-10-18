import type { ExpressionSpecification, GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { FeatureCollection, MultiLineString } from 'geojson';
import type { LineCatalogue } from './lines';

// Every line of the network, faint, under the vehicles (E3.8, ADR-0016): decorative, the text view lists the lines.

const SOURCE = 'shapes';
// A line has several shapes (directions, variants) that overlap: transparent lines would add up where they do. Each
// line is drawn opaque instead, in its colour mixed with Positron's background (#F2F3F0), which looks transparent.
const BACKGROUND = [0xf2, 0xf3, 0xf0];
const STRENGTH = 0.35;
// The selected line (E3.4) is drawn again over the others, at the same width so that it hides no more of the map, in
// its full colour. A third of TBM's colours stay under 3:1 on the basemap even then: those are darkened towards the
// markers' outline, just enough to reach it (WCAG 1.4.11), which keeps their hue.
const SELECTED_CONTRAST = 3;
const DARK = [0x1f, 0x29, 0x37];
const WIDTH: ExpressionSpecification = ['interpolate', ['linear'], ['zoom'], 10, 1.5, 16, 4];
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
                'line-width': WIDTH,
            },
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
            paint: { 'line-color': colours(contrasted), 'line-width': WIDTH },
        },
        firstLabels,
    );

    // Colours live in the line catalogue, not in the file: a paint expression, so a new catalogue never rebuilds the tiles.
    function colours(paint: (colour: string) => string = faint): ExpressionSpecification {
        const byLine = Object.fromEntries([...lines.all()].map((line) => [line.id, paint(line.colour)]));

        return ['to-color', ['coalesce', ['get', ['get', 'line'], ['literal', byLine]], paint(neutralColour)]];
    }

    return {
        show(shapes) {
            map.getSource<GeoJSONSource>(SOURCE)?.setData(shapes);
            map.once('idle', () => (map.getContainer().dataset.shapeCount = String(shapes.features.length)));
        },

        recolour() {
            map.setPaintProperty('shapes', 'line-color', colours());
            map.setPaintProperty('shapes-selected', 'line-color', colours(contrasted));
        },

        highlight(lineId) {
            map.setFilter('shapes-selected', ['==', ['get', 'line'], lineId ?? NONE]);
            map.getContainer().dataset.highlightedLine = lineId ?? '';
        },
    };
}

function faint(colour: string): string {
    return toHex(mix(channels(colour), BACKGROUND, STRENGTH));
}

/**
 * The colour itself when it reaches 3:1 on the basemap, else mixed with dark grey in steps of 5 % until it does.
 */
function contrasted(colour: string): string {
    const own = channels(colour);
    let mixed = own;

    for (let dark = 0.05; contrast(mixed, BACKGROUND) < SELECTED_CONTRAST && dark <= 1; dark += 0.05) {
        mixed = mix(DARK, own, dark);
    }

    return toHex(mixed);
}

function channels(colour: string): number[] {
    return [1, 3, 5].map((start) => parseInt(colour.slice(start, start + 2), 16));
}

function mix(from: number[], to: number[], strength: number): number[] {
    return from.map((value, index) => Math.round(strength * value + (1 - strength) * to[index]!));
}

function toHex(rgb: number[]): string {
    return `#${rgb.map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}

// WCAG 2 contrast ratio between two colours.
function contrast(a: number[], b: number[]): number {
    const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number];

    return (light + 0.05) / (dark + 0.05);
}

function luminance(rgb: number[]): number {
    const [r, g, b] = rgb.map((value) => {
        const channel = value / 255;

        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    }) as [number, number, number];

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
