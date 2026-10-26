import type { IControl, Map as MapLibreMap } from 'maplibre-gl';

/**
 * What the map can be brought back to: the vehicle or the station whose popup is open.
 */
export interface RecentreTarget {
    label: string;
    coordinates: [number, number];
}

/**
 * The map never moves on its own (E3.4): a vehicle drives its popup out of view. This button, under the zoom, brings
 * the open popup's vehicle or station back to the centre; it shows only while there is one.
 */
export class RecentreControl implements IControl {
    private readonly container = document.createElement('div');
    private readonly button = document.createElement('button');

    constructor(private readonly target: () => RecentreTarget | null) {
        this.container.className = 'maplibregl-ctrl maplibregl-ctrl-group';
        this.container.hidden = true;
        this.button.type = 'button';
        this.button.dataset.recentre = '';
        // A crosshair, drawn like MapLibre's own controls; the name is the button's label.
        this.button.innerHTML =
            '<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false" style="margin:auto"><circle cx="10" cy="10" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M10 1v5M10 14v5M1 10h5M14 10h5" stroke="currentColor" stroke-width="2"/></svg>';
        this.container.append(this.button);
    }

    onAdd(map: MapLibreMap): HTMLElement {
        this.button.addEventListener('click', () => {
            const target = this.target();

            if (target !== null) {
                map.easeTo({ center: target.coordinates });
            }
        });

        return this.container;
    }

    onRemove(): void {
        this.container.remove();
    }

    /**
     * Called when a popup opens or closes, or its vehicle changes.
     */
    refresh(): void {
        const target = this.target();
        this.container.hidden = target === null;

        if (target !== null) {
            const label = `Recentrer la carte sur ${target.label}`;
            this.button.setAttribute('aria-label', label);
            this.button.title = label;
        }
    }
}
