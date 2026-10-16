import { type Bounds, mountMap } from './map/index';

// Entry of the map page only (ADR-0011): the other pages never load MapLibre.
const container = document.querySelector<HTMLElement>('[data-map]');

if (container !== null) {
    mountMap(container, {
        styleUrl: container.dataset.style ?? '',
        bounds: JSON.parse(container.dataset.bounds ?? 'null') as Bounds | null,
        label: container.dataset.label ?? 'Carte',
        vehiclesUrl: container.dataset.vehiclesUrl ?? '',
        refreshSeconds: Number(container.dataset.refresh ?? 20),
    });
}
