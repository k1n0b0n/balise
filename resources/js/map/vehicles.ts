import type { GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { Feature, FeatureCollection, Point } from 'geojson';
import type { Vehicle } from './live';

// Dark grey reaches 13.9:1 on Positron's background (#F2F3F0): marker outlines pass 3:1 and labels 4.5:1,
// whatever the line colour. The white halo keeps labels readable over roads and other markers.
const DARK = '#1F2937';
const UNKNOWN_LINE = '?';
const SOURCE = 'vehicles';
const PIXEL_RATIO = 2;

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

/**
 * Draws vehicles as small markers shaped by mode, in their line's colour, labelled beside the marker; returns the
 * function that updates them.
 */
export function showVehicles(map: MapLibreMap): (vehicles: Vehicle[]) => void {
    map.addSource(SOURCE, { type: 'geojson', data: toGeoJson([]) });

    // One image per shape and colour, drawn the first time the map needs it.
    map.setMissingStyleImageResolver((id) => {
        const [prefix, shape, colour] = id.split(':');
        if (prefix === 'vehicle' && shape !== undefined && shape in SHAPES && colour !== undefined) {
            map.addImage(id, drawMarker(shape as Shape, colour), { pixelRatio: PIXEL_RATIO });
        }
    });

    // Markers always show; MapLibre hides the labels that would overlap.
    map.addLayer({
        id: 'vehicles',
        type: 'symbol',
        source: SOURCE,
        layout: {
            'icon-image': ['concat', 'vehicle:', ['get', 'shape'], ':', ['get', 'colour']],
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'text-field': ['get', 'label'],
            'text-font': ['Noto Sans Bold'],
            'text-size': 11,
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

    return (vehicles) => {
        map.getSource<GeoJSONSource>(SOURCE)?.setData(toGeoJson(vehicles));
        map.getContainer().dataset.vehicleCount = String(vehicles.length);
    };
}

function toGeoJson(vehicles: Vehicle[]): FeatureCollection<Point> {
    return {
        type: 'FeatureCollection',
        features: vehicles.map(
            (vehicle): Feature<Point> => ({
                type: 'Feature',
                id: vehicle.id,
                geometry: { type: 'Point', coordinates: [vehicle.longitude, vehicle.latitude] },
                properties: { label: vehicle.line ?? UNKNOWN_LINE, shape: shapeOf(vehicle.mode), colour: vehicle.colour },
            }),
        ),
    };
}

function drawMarker(shape: Shape, colour: string): ImageData {
    const size = SHAPES[shape] * PIXEL_RATIO;
    const side = Math.ceil(size + 2 * PIXEL_RATIO);
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

    context.fillStyle = colour;
    context.fill();
    context.lineWidth = PIXEL_RATIO;
    context.strokeStyle = DARK;
    context.stroke();

    return context.getImageData(0, 0, side, side);
}
