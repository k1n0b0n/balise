import { type Bounds, mountMap } from './map/index';

// Entry of the map page only (ADR-0011): the other pages never load MapLibre.
const container = document.querySelector<HTMLElement>('[data-map]');

if (container !== null) {
    mountMap(container, document.querySelector<HTMLElement>('[data-text-view]'), document.querySelector<HTMLButtonElement>('[data-pause]'), {
        styleUrl: container.dataset.style ?? '',
        bounds: JSON.parse(container.dataset.bounds ?? 'null') as Bounds | null,
        label: container.dataset.label ?? 'Carte',
        vehiclesUrl: container.dataset.vehiclesUrl ?? '',
        linesUrl: container.dataset.linesUrl ?? '',
        shapesUrl: container.dataset.shapesUrl ?? '',
        neutralColour: container.dataset.neutralColour ?? '#4B5563',
        refreshSeconds: Number(container.dataset.refresh ?? 20),
    });
}
