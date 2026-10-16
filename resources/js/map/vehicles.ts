import { type FilterSpecification, type GeoJSONSource, LngLat, type Map as MapLibreMap, Marker } from 'maplibre-gl';
import type { Feature, FeatureCollection, Point } from 'geojson';
import type { Vehicle } from './live';

// Dark grey outline: 13.9:1 against Positron's background (#F2F3F0), so every point reaches 3:1 whatever its line colour.
const OUTLINE = '#1F2937';
const UNKNOWN_LINE = '?';
const SOURCE = 'vehicles';
const IS_POINT: FilterSpecification = ['!', ['has', 'point_count']];

/**
 * Draws vehicles as points in their line's colour, grouped at low zoom; returns the function that updates them.
 *
 * Groups are HTML buttons rather than canvas shapes, so that their vehicle count has an accessible name and the
 * keyboard can reach them.
 */
export function showVehicles(map: MapLibreMap): (vehicles: Vehicle[]) => void {
    map.addSource(SOURCE, { type: 'geojson', data: toGeoJson([]), cluster: true, clusterMaxZoom: 13, clusterRadius: 40 });

    map.addLayer({
        id: 'vehicles',
        type: 'circle',
        source: SOURCE,
        filter: IS_POINT,
        paint: {
            // Wide enough for the longest short names ("Artigues").
            'circle-radius': ['max', 10, ['+', 3, ['*', 3.5, ['length', ['get', 'label']]]]],
            'circle-color': ['get', 'colour'],
            'circle-stroke-width': 1.5,
            'circle-stroke-color': OUTLINE,
        },
    });

    map.addLayer({
        id: 'vehicle-labels',
        type: 'symbol',
        source: SOURCE,
        filter: IS_POINT,
        layout: {
            'text-field': ['get', 'label'],
            'text-font': ['Noto Sans Bold'],
            'text-size': 10,
            'text-allow-overlap': true,
            'text-ignore-placement': true,
        },
        paint: { 'text-color': ['get', 'textColour'] },
    });

    const groups = new GroupMarkers(map);
    map.on('render', () => {
        if (map.isSourceLoaded(SOURCE)) {
            groups.update();
        }
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
                properties: { label: vehicle.line ?? UNKNOWN_LINE, colour: vehicle.colour, textColour: vehicle.textColour },
            }),
        ),
    };
}

class GroupMarkers {
    private readonly markers = new Map<number, Marker>();

    constructor(private readonly map: MapLibreMap) {}

    update(): void {
        const groups = new Map<number, { coordinates: [number, number]; count: number }>();

        for (const feature of this.map.querySourceFeatures(SOURCE, { filter: ['has', 'point_count'] })) {
            const properties = feature.properties as { cluster_id: number; point_count: number };
            const [longitude, latitude] = (feature.geometry as Point).coordinates;
            groups.set(properties.cluster_id, { coordinates: [longitude ?? 0, latitude ?? 0], count: properties.point_count });
        }

        // Group ids change with every update of the data: a focused button moves to the closest new group instead of disappearing.
        const free = [...this.markers].filter(([id]) => !groups.has(id));
        const focused = free.find(([, marker]) => marker.getElement().contains(document.activeElement));

        for (const [id, marker] of free) {
            if (marker !== focused?.[1]) {
                marker.remove();
            }
            this.markers.delete(id);
        }

        if (focused !== undefined) {
            const closest = this.closestNewGroup(focused[1], groups);
            if (closest === undefined) {
                focused[1].remove();
            } else {
                this.markers.set(closest, focused[1]);
            }
        }

        for (const [id, group] of groups) {
            const marker = this.markers.get(id) ?? this.add(id);
            const button = marker.getElement();
            marker.setLngLat(group.coordinates);
            button.dataset.group = String(id);
            button.textContent = String(group.count);
            button.setAttribute('aria-label', `${group.count} véhicules, zoomer sur le groupe`);
        }
    }

    private add(id: number): Marker {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'grid size-9 cursor-pointer place-items-center rounded-full border-2 border-white bg-gray-800 text-sm font-semibold text-white shadow';
        button.addEventListener('click', () => void this.zoomInto(button));

        const marker = new Marker({ element: button }).setLngLat([0, 0]).addTo(this.map);
        this.markers.set(id, marker);

        return marker;
    }

    private async zoomInto(button: HTMLElement): Promise<void> {
        const marker = this.markers.get(Number(button.dataset.group));
        const source = this.map.getSource<GeoJSONSource>(SOURCE);

        if (marker === undefined || source === undefined) {
            return;
        }

        const zoom = await source.getClusterExpansionZoom(Number(button.dataset.group));
        this.map.easeTo({ center: marker.getLngLat(), zoom });
    }

    private closestNewGroup(marker: Marker, groups: Map<number, { coordinates: [number, number] }>): number | undefined {
        const from = marker.getLngLat();
        let closest: number | undefined;
        let shortest = Infinity;

        for (const [id, group] of groups) {
            const distance = this.markers.has(id) ? Infinity : from.distanceTo(LngLat.convert(group.coordinates));
            if (distance < shortest) {
                [closest, shortest] = [id, distance];
            }
        }

        return closest;
    }
}
