import type { ExpressionSpecification, GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { Feature, FeatureCollection, Point } from 'geojson';
import type { Vehicle } from './live';
import type { LineCatalogue } from './lines';
import { VehiclePopup } from './popup';

// Dark grey reaches 13.9:1 on Positron's background (#F2F3F0): marker outlines pass 3:1 and labels 4.5:1,
// whatever the line colour. The white halo keeps labels readable over roads and other markers.
const DARK = '#1F2937';
const UNKNOWN_LINE = '?';
const SOURCE = 'vehicles';
const PIXEL_RATIO = 2;
// Markers are 7 px wide: a click counts if it lands this close.
const CLICK_TOLERANCE = 6;
// A vehicle opened from the text view is shown at least at street level.
const FOCUS_ZOOM = 14;
// The selected line's vehicles (E3.4) are drawn larger, over the others, with a white ring that sets them apart from
// their line's shape, drawn in the same colour under them; the others stay as they are.
const SELECTED_SCALE = 1.5;
const RING = 2;
const NONE = '';

type Shape = 'circle' | 'square' | 'diamond' | 'triangle';

// The shape tells the mode without relying on colour (RGAA 3.1); sizes in CSS pixels, outline excluded.
const SHAPES: Record<Shape, number> = { circle: 7, square: 6.5, diamond: 8, triangle: 8 };

function shapeOf(mode: string | null): Shape {
    switch (mode) {
        case 'bus':
            return 'circle';
        case 'tram':
            return 'square';
        case 'ferry':
            return 'diamond';
        default:
            return 'triangle';
    }
}

export interface VehicleLayer {
    update(vehicles: Vehicle[]): void;
    open(id: string): void;
    highlight(lineId: string | null): void;
}

/**
 * Draws vehicles as small markers shaped by mode, in their line's colour, labelled beside the marker.
 */
export function showVehicles(map: MapLibreMap, lines: LineCatalogue, neutralColour: string, onSelect: (vehicle: Vehicle | null) => void): VehicleLayer {
    map.addSource(SOURCE, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });

    // One image per shape, colour and state, drawn the first time the map needs it.
    map.setMissingStyleImageResolver((id) => {
        const [prefix, shape, colour, state] = id.split(':');
        if (prefix === 'vehicle' && shape !== undefined && shape in SHAPES && colour !== undefined) {
            map.addImage(id, drawMarker(shape as Shape, colour, state === 'selected'), { pixelRatio: PIXEL_RATIO });
        }
    });

    // Markers always show; MapLibre hides the labels that would overlap.
    map.addLayer({
        id: 'vehicles',
        type: 'symbol',
        source: SOURCE,
        layout: {
            ...highlighted(NONE),
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'text-field': ['get', 'label'],
            'text-font': ['Noto Sans Bold'],
            'text-anchor': 'left',
            'text-offset': [0.5, 0],
            'text-optional': true,
        },
        paint: {
            'text-color': DARK,
            'text-halo-color': '#FFFFFF',
            'text-halo-width': 1.5,
        },
    });

    let current = new Map<string, Vehicle>();
    const popup = new VehiclePopup(map, lines, onSelect);

    // Keyboard users open the same popup from the text view (E3.5): canvas markers cannot take focus (ADR-0011).
    // Markers may be stacked, at a terminus for instance: the popup opens on the first and its arrows go through the rest.
    map.on('click', ({ point }) => {
        const features = map.queryRenderedFeatures(
            [
                [point.x - CLICK_TOLERANCE, point.y - CLICK_TOLERANCE],
                [point.x + CLICK_TOLERANCE, point.y + CLICK_TOLERANCE],
            ],
            { layers: ['vehicles'] },
        );
        const ids = [...new Set(features.map((feature) => String(feature.properties.id)))].filter((id) => current.has(id)).sort();

        if (ids.length === 0) {
            popup.close();

            return;
        }

        const vehicle = current.get(ids[0] ?? '');

        if (vehicle !== undefined) {
            popup.open(vehicle, ids);
        }
    });
    map.on('mousemove', 'vehicles', () => (map.getCanvas().style.cursor = 'pointer'));
    map.on('mouseleave', 'vehicles', () => (map.getCanvas().style.cursor = ''));

    return {
        update(vehicles) {
            current = new Map(vehicles.map((vehicle) => [vehicle.id, vehicle]));
            map.getSource<GeoJSONSource>(SOURCE)?.setData(toGeoJson(vehicles));
            // Lets browser tests know the vehicles are drawn: the worker processes the data, the map renders it later.
            map.once('idle', () => (map.getContainer().dataset.vehicleCount = String(vehicles.length)));
            popup.update(current);
        },

        highlight(lineId) {
            const layout = highlighted(lineId ?? NONE);
            for (const property of Object.keys(layout) as (keyof typeof layout)[]) {
                map.setLayoutProperty('vehicles', property, layout[property]);
            }
        },

        // From the text view: bring the vehicle into view, then open its popup, which takes the focus.
        open(id) {
            const vehicle = current.get(id);

            if (vehicle !== undefined) {
                map.easeTo({ center: [vehicle.longitude, vehicle.latitude], zoom: Math.max(map.getZoom(), FOCUS_ZOOM) });
                popup.open(vehicle);
            }
        },
    };

    function toGeoJson(vehicles: Vehicle[]): FeatureCollection<Point> {
        return {
            type: 'FeatureCollection',
            features: vehicles.map((vehicle): Feature<Point> => {
                // A line the catalogue lacks, or a catalogue not loaded yet, shows like no line at all (ADR-0013).
                const line = lines.get(vehicle.lineId);

                return {
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [vehicle.longitude, vehicle.latitude] },
                    // Feature ids must be numbers in MapLibre: the vehicle id travels as a property.
                    properties: { id: vehicle.id, line: line?.id ?? NONE, label: line?.shortName ?? UNKNOWN_LINE, shape: shapeOf(line?.mode ?? null), colour: line?.colour ?? neutralColour },
                };
            }),
        };
    }
}

/**
 * The layout properties that set the selected line's vehicles apart; no line selected matches no vehicle.
 */
function highlighted(lineId: string): Record<'icon-image' | 'icon-size' | 'symbol-sort-key' | 'text-size', ExpressionSpecification> {
    const selected: ExpressionSpecification = ['all', ['!=', lineId, NONE], ['==', ['get', 'line'], lineId]];

    return {
        'icon-image': ['concat', 'vehicle:', ['get', 'shape'], ':', ['get', 'colour'], ['case', selected, ':selected', '']],
        'icon-size': ['case', selected, SELECTED_SCALE, 1],
        'symbol-sort-key': ['case', selected, 1, 0],
        'text-size': ['case', selected, 13, 11],
    };
}

function drawMarker(shape: Shape, colour: string, selected: boolean): ImageData {
    const size = SHAPES[shape] * PIXEL_RATIO;
    const side = Math.ceil(size + 2 * PIXEL_RATIO * (selected ? 1 + RING : 1));
    const centre = side / 2;
    const half = size / 2;
    const context = new OffscreenCanvas(side, side).getContext('2d');

    if (context === null) {
        throw new Error('Canvas 2D is not available.');
    }

    context.beginPath();
    switch (shape) {
        case 'circle':
            context.arc(centre, centre, half, 0, 2 * Math.PI);
            break;
        case 'square':
            context.rect(centre - half, centre - half, size, size);
            break;
        case 'diamond':
            context.moveTo(centre, centre - half);
            context.lineTo(centre + half, centre);
            context.lineTo(centre, centre + half);
            context.lineTo(centre - half, centre);
            break;
        case 'triangle':
            context.moveTo(centre, centre - half);
            context.lineTo(centre + half, centre + half * 0.8);
            context.lineTo(centre - half, centre + half * 0.8);
            break;
    }
    context.closePath();

    // The ring goes outside the dark outline, which stays next to the colour for its 3:1.
    if (selected) {
        context.lineWidth = PIXEL_RATIO * (1 + 2 * RING);
        context.strokeStyle = '#FFFFFF';
        context.stroke();
    }

    context.fillStyle = colour;
    context.fill();
    context.lineWidth = PIXEL_RATIO;
    context.strokeStyle = DARK;
    context.stroke();

    return context.getImageData(0, 0, side, side);
}
