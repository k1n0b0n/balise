import type { GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';
import type { Feature, FeatureCollection, Point } from 'geojson';
import type { Vehicle } from './live';

// Dark grey reaches 13.9:1 on Positron's background (#F2F3F0): point outlines pass 3:1 and labels 4.5:1,
// whatever the line colour. The white halo keeps labels readable over roads and other points.
const DARK = '#1F2937';
const UNKNOWN_LINE = '?';
const SOURCE = 'vehicles';

/**
 * Draws vehicles as small points in their line's colour, labelled beside the point; returns the function that updates them.
 */
export function showVehicles(map: MapLibreMap): (vehicles: Vehicle[]) => void {
    map.addSource(SOURCE, { type: 'geojson', data: toGeoJson([]) });

    map.addLayer({
        id: 'vehicles',
        type: 'circle',
        source: SOURCE,
        paint: {
            'circle-radius': 3.5,
            'circle-color': ['get', 'colour'],
            'circle-stroke-width': 1,
            'circle-stroke-color': DARK,
        },
    });

    // MapLibre hides labels that would overlap; the points always show.
    map.addLayer({
        id: 'vehicle-labels',
        type: 'symbol',
        source: SOURCE,
        layout: {
            'text-field': ['get', 'label'],
            'text-font': ['Noto Sans Bold'],
            'text-size': 11,
            'text-anchor': 'left',
            'text-offset': [0.5, 0],
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
                properties: { label: vehicle.line ?? UNKNOWN_LINE, colour: vehicle.colour },
            }),
        ),
    };
}
