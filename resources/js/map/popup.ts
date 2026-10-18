import { type Map as MapLibreMap, Popup } from 'maplibre-gl';
import { displayId, type Vehicle } from './live';
import type { LineCatalogue } from './lines';

/**
 * "il y a 25 s": how long ago a position was recorded; a position slightly in the future counts as now.
 */
export function formatAge(recordedAt: string, now: number = Date.now()): string {
    const seconds = Math.max(0, Math.round((now - Date.parse(recordedAt)) / 1000));

    if (seconds === 0) {
        return 'à l’instant';
    }
    if (seconds < 60) {
        return `il y a ${seconds} s`;
    }

    const minutes = Math.floor(seconds / 60);

    return minutes < 60 ? `il y a ${minutes} min` : `il y a ${Math.floor(minutes / 60)} h`;
}

/**
 * One popup at a time, following its vehicle on each update (E3.3). Escape and the close button give the focus back
 * to the element that had it when the popup opened.
 */
export class VehiclePopup {
    private readonly popup = new Popup({ closeOnClick: false, maxWidth: '18rem' });
    private vehicle: Vehicle | null = null;
    private returnFocusTo: Element | null = null;
    private ticking: number | undefined;

    constructor(
        private readonly map: MapLibreMap,
        private readonly lines: LineCatalogue,
        // The open vehicle is the selected one (E3.4): told when it opens, and with null when the popup closes.
        private readonly onSelect: (vehicle: Vehicle | null) => void,
    ) {
        this.popup.on('close', () => this.closed());

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.popup.isOpen()) {
                this.popup.remove();
            }
        });
    }

    open(vehicle: Vehicle): void {
        if (!this.popup.isOpen()) {
            this.returnFocusTo = document.activeElement;
        }

        this.vehicle = vehicle;
        this.render();
        this.popup.addTo(this.map);

        const element = this.popup.getElement();
        element.setAttribute('role', 'dialog');
        element.setAttribute('aria-label', `Véhicule ${displayId(vehicle)}`);

        // The age changes every second; it is not a live region, so screen readers are not flooded.
        window.clearInterval(this.ticking);
        this.ticking = window.setInterval(() => this.renderAge(), 1000);
        this.onSelect(vehicle);
    }

    /**
     * Follows the open vehicle to its new position, or closes when it is no longer on the map.
     */
    update(vehicles: ReadonlyMap<string, Vehicle>): void {
        if (this.vehicle === null || !this.popup.isOpen()) {
            return;
        }

        const vehicle = vehicles.get(this.vehicle.id);

        if (vehicle === undefined) {
            this.popup.remove();
        } else {
            this.vehicle = vehicle;
            this.render();
        }
    }

    close(): void {
        this.popup.remove();
    }

    private render(): void {
        if (this.vehicle === null) {
            return;
        }

        const { latitude, longitude, recordedAt } = this.vehicle;
        const line = this.lines.get(this.vehicle.lineId);
        const list = document.createElement('dl');
        list.className = 'grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 pr-5 text-sm';

        for (const [term, value] of [
            ['Ligne', line === undefined ? 'Ligne inconnue' : [line.shortName, line.longName].filter(Boolean).join(' – ')],
            ['Véhicule', displayId(this.vehicle)],
            ['Position', formatAge(recordedAt)],
        ] as const) {
            const dt = document.createElement('dt');
            const dd = document.createElement('dd');
            dt.className = 'font-semibold';
            dt.textContent = term;
            dd.textContent = value;
            list.append(dt, dd);
        }

        this.popup.setLngLat([longitude, latitude]).setDOMContent(list);
    }

    private renderAge(): void {
        const age = this.popup.getElement()?.querySelector('dd:last-of-type');

        if (age && this.vehicle !== null) {
            age.textContent = formatAge(this.vehicle.recordedAt);
        }
    }

    private closed(): void {
        window.clearInterval(this.ticking);
        this.vehicle = null;
        this.onSelect(null);

        // Give the focus back only if it was in the popup, which is now gone; a click elsewhere keeps its target.
        if (document.activeElement === document.body && this.returnFocusTo instanceof HTMLElement) {
            this.returnFocusTo.focus();

            // Hidden meanwhile, in a closed panel for instance: the page shows it again and gives it the focus.
            if (document.activeElement !== this.returnFocusTo) {
                document.dispatchEvent(new CustomEvent('vehicle-popup-focus-lost', { detail: this.returnFocusTo }));
            }
        }

        this.returnFocusTo = null;
    }
}
