import { type Bounds, mountMap } from './map/index';
import { followReplayClock } from './map/replay-clock';

// Entry of the map page only (ADR-0011): the other pages never load MapLibre.
const container = document.querySelector<HTMLElement>('[data-map]');

if (container !== null) {
    const controls = {
        textView: document.querySelector<HTMLElement>('[data-text-view]'),
        pause: document.querySelector<HTMLButtonElement>('[data-pause]'),
        lineFilter: document.querySelector<HTMLFormElement>('[data-line-filter]'),
        stationSearch: document.querySelector<HTMLFormElement>('[data-station-search]'),
    };

    mountMap(container, controls, {
        styleUrl: container.dataset.style ?? '',
        bounds: JSON.parse(container.dataset.bounds ?? 'null') as Bounds | null,
        label: container.dataset.label ?? 'Carte',
        vehiclesUrl: container.dataset.vehiclesUrl ?? '',
        linesUrl: container.dataset.linesUrl ?? '',
        shapesUrl: container.dataset.shapesUrl ?? '',
        stationsUrl: container.dataset.stationsUrl ?? '',
        neutralColour: container.dataset.neutralColour ?? '#4B5563',
        refreshSeconds: Number(container.dataset.refresh ?? 20),
        timeZone: container.dataset.timezone ?? 'Europe/Paris',
    });
}

const replayClock = document.querySelector<HTMLTimeElement>('[data-replay-clock]');

if (replayClock !== null) {
    followReplayClock(replayClock, replayClock.dataset.timezone ?? 'Europe/Paris');
}
