import { displayId, type Vehicle } from './live';
import type { LineCatalogue } from './lines';
import { formatAge } from './popup';

// Text view equivalent to the map (E3.5, ADR-0006): the server renders it, this module keeps it current with the same
// data as the map. Updates never move the focus, and only the status line is a live region.

const AGE_EVERY_SECONDS = 5;
const UNKNOWN_LINE = '';

interface Group {
    element: HTMLElement;
    heading: HTMLElement;
    list: HTMLElement;
}

interface Row {
    element: HTMLLIElement;
    lineName: HTMLElement;
    age: HTMLTimeElement;
    vehicle: Vehicle;
}

export interface TextView {
    update(vehicles: Vehicle[]): void;
    updated(at: Date): void;
    paused(): void;
    resumed(): void;
}

export function showTextView(root: HTMLElement, lines: LineCatalogue, open: (id: string) => void): TextView {
    const container = required(root, '[data-lines]');
    const status = required(root, '[data-status]');
    const collator = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });
    const clock = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: root.dataset.timezone });
    const groups = new Map<string, Group>();
    const rows = new Map<string, Row>();
    let empty: Element | null = null;
    let rendered = false;
    let lastUpdate: Date | null = null;

    root.addEventListener('click', (event) => {
        const id = (event.target as Element).closest('[data-open]')?.closest<HTMLElement>('[data-vehicle]')?.dataset.vehicle;

        if (id !== undefined) {
            open(id);
        }
    });

    window.setInterval(() => {
        if (!document.hidden) {
            rows.forEach(renderAge);
        }
    }, AGE_EVERY_SECONDS * 1000);

    function update(vehicles: Vehicle[]): void {
        if (!rendered) {
            // The server's rows are plain text; from now on they are rebuilt with their buttons.
            container.replaceChildren();
            rendered = true;
        }

        const byGroup = new Map<string, Vehicle[]>();
        for (const vehicle of vehicles) {
            const key = lines.get(vehicle.lineId)?.id ?? UNKNOWN_LINE;
            byGroup.set(key, [...(byGroup.get(key) ?? []), vehicle]);
        }

        const present = new Set(vehicles.map((vehicle) => vehicle.id));
        for (const [id, row] of rows) {
            if (!present.has(id)) {
                remove(row.element);
                rows.delete(id);
            }
        }

        if (vehicles.length === 0) {
            empty ??= container.appendChild(template(root, '[data-empty-template]'));
        } else if (empty !== null) {
            empty.remove();
            empty = null;
        }

        let previousGroup: Element | null = null;
        for (const key of [...byGroup.keys()].sort(compareGroups)) {
            const group = groups.get(key) ?? createGroup(key);
            group.heading.textContent = headingOf(key);
            const lineName = lineNameOf(key);
            place(container, group.element, previousGroup);
            previousGroup = group.element;

            let previousRow: Element | null = null;
            for (const vehicle of (byGroup.get(key) ?? []).sort((a, b) => collator.compare(displayId(a), displayId(b)))) {
                const row = rows.get(vehicle.id) ?? createRow(vehicle);
                row.vehicle = vehicle;
                // Keyboard users reach the buttons with Tab, past the headings: the button's name carries the line.
                if (row.lineName.textContent !== lineName) {
                    row.lineName.textContent = lineName;
                }
                renderAge(row);
                place(group.list, row.element, previousRow);
                previousRow = row.element;
            }
        }

        for (const [key, group] of groups) {
            if (!byGroup.has(key)) {
                remove(group.element);
                groups.delete(key);
            }
        }
    }

    function updated(at: Date): void {
        lastUpdate = at;
        // Worded to the minute and written only when it changes: screen readers hear it at most once a minute.
        setStatus(`Mis à jour à ${clock.format(at)}`);
    }

    function paused(): void {
        setStatus(lastUpdate === null ? 'Mises à jour en pause' : `Mises à jour en pause, dernière à ${clock.format(lastUpdate)}`);
    }

    // The catch-up request may find nothing new and leave the status alone: say at once that updates are back.
    function resumed(): void {
        setStatus(lastUpdate === null ? 'En attente des positions' : `Mis à jour à ${clock.format(lastUpdate)}`);
    }

    function setStatus(text: string): void {
        if (status.textContent?.trim() !== text) {
            status.textContent = text;
        }
    }

    function compareGroups(a: string, b: string): number {
        if (a === UNKNOWN_LINE || b === UNKNOWN_LINE) {
            return Number(a === UNKNOWN_LINE) - Number(b === UNKNOWN_LINE);
        }

        return collator.compare(lines.get(a)?.shortName ?? '', lines.get(b)?.shortName ?? '');
    }

    function headingOf(key: string): string {
        const line = lines.get(key);

        if (line === undefined) {
            return 'Ligne inconnue';
        }

        return `${line.modeName} ${line.shortName}${line.longName === null ? '' : ` – ${line.longName}`}`;
    }

    function lineNameOf(key: string): string {
        const line = lines.get(key);

        return line === undefined ? 'ligne inconnue' : `${line.modeName} ${line.shortName}`;
    }

    function createGroup(key: string): Group {
        const element = template(root, '[data-line-template]');
        const group = { element, heading: required(element, '[data-heading]'), list: required(element, '[data-vehicles]') };
        groups.set(key, group);

        return group;
    }

    function createRow(vehicle: Vehicle): Row {
        const element = template(root, '[data-vehicle-template]') as HTMLLIElement;
        element.dataset.vehicle = vehicle.id;
        required(element, '[data-name]').textContent = displayId(vehicle);
        const row = { element, lineName: required(element, '[data-line-name]'), age: required(element, '[data-age]') as HTMLTimeElement, vehicle };
        rows.set(vehicle.id, row);

        return row;
    }

    function remove(element: Element): void {
        // The one case where the focus moves: its element leaves the page, so it goes to the text view, not to the body.
        if (element.contains(document.activeElement)) {
            root.focus();
        }
        element.remove();
    }

    return { update, updated, paused, resumed };
}

function renderAge(row: Row): void {
    row.age.dateTime = row.vehicle.recordedAt;
    row.age.textContent = formatAge(row.vehicle.recordedAt);
}

/**
 * Puts the element right after `previous` (first when null), moving it only if it is not there yet, so that a focused
 * button keeps the focus.
 */
function place(parent: Element, element: Element, previous: Element | null): void {
    const expected = previous === null ? parent.firstElementChild : previous.nextElementSibling;

    if (expected === element) {
        return;
    }

    const focused = element.contains(document.activeElement) ? document.activeElement : null;
    parent.insertBefore(element, expected);

    if (focused instanceof HTMLElement) {
        focused.focus();
    }
}

function template(root: Element, selector: string): HTMLElement {
    const copy = (required(root, selector) as HTMLTemplateElement).content.firstElementChild?.cloneNode(true);

    if (!(copy instanceof HTMLElement)) {
        throw new Error(`Empty template ${selector}.`);
    }

    return copy;
}

function required(root: Element, selector: string): HTMLElement {
    const element = root.querySelector<HTMLElement>(selector);

    if (element === null) {
        throw new Error(`Text view: ${selector} is missing.`);
    }

    return element;
}
