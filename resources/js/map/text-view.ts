import { displayId, type Vehicle } from './live';
import { lineLabel, type Line, type LineCatalogue, vehicleName } from './lines';
import { formatAge } from './popup';

// Text view equivalent to the map (E3.5, ADR-0006): the server renders it, this module keeps it current with the same
// data as the map. Updates never move the focus, and only the status line is a live region.

const AGE_EVERY_SECONDS = 5;
const UNKNOWN_LINE = '';
const UNKNOWN_DESTINATION = '';

// A line, with its destinations (E3.7).
interface Group {
    element: HTMLElement;
    heading: HTMLElement;
    destinations: HTMLElement;
    subgroups: Map<string, Subgroup>;
}

// A destination of a line, with its vehicles.
interface Subgroup {
    element: HTMLElement;
    heading: HTMLElement;
    list: HTMLElement;
}

interface Row {
    element: HTMLLIElement;
    name: HTMLElement;
    detail: HTMLElement;
    age: HTMLTimeElement;
    vehicle: Vehicle;
}

export interface TextView {
    update(vehicles: Vehicle[], firstLineId: string | null): void;
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
    // The selected line's group comes first (E3.4); the others keep their order.
    let firstLine: string | null = null;

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

    function update(vehicles: Vehicle[], firstLineId: string | null): void {
        firstLine = firstLineId;

        if (!rendered) {
            // The server's rows are plain text; from now on they are rebuilt with their buttons.
            container.replaceChildren();
            rendered = true;
        }

        const byGroup = new Map<string, Map<string, Vehicle[]>>();
        for (const vehicle of vehicles) {
            const key = lines.get(vehicle.lineId)?.id ?? UNKNOWN_LINE;
            const destinations = byGroup.get(key) ?? new Map<string, Vehicle[]>();
            byGroup.set(key, destinations);
            const destination = vehicle.destination ?? UNKNOWN_DESTINATION;
            destinations.set(destination, [...(destinations.get(destination) ?? []), vehicle]);
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

            const destinations = byGroup.get(key) ?? new Map<string, Vehicle[]>();
            let previousSubgroup: Element | null = null;
            for (const destination of [...destinations.keys()].sort(compareDestinations)) {
                const subgroup = group.subgroups.get(destination) ?? createSubgroup(group, destination);
                subgroup.heading.textContent = destination === UNKNOWN_DESTINATION ? 'Destination inconnue' : destination;
                place(group.destinations, subgroup.element, previousSubgroup);
                previousSubgroup = subgroup.element;

                let previousRow: Element | null = null;
                for (const vehicle of (destinations.get(destination) ?? []).sort((a, b) => collator.compare(displayId(a), displayId(b)))) {
                    const row = rows.get(vehicle.id) ?? createRow(vehicle);
                    row.vehicle = vehicle;
                    renderName(row, lineName, lines.get(key));
                    renderAge(row);
                    place(subgroup.list, row.element, previousRow);
                    previousRow = row.element;
                }
            }

            for (const [destination, subgroup] of group.subgroups) {
                if (!destinations.has(destination)) {
                    remove(subgroup.element);
                    group.subgroups.delete(destination);
                }
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
        if (a === firstLine || b === firstLine) {
            return Number(b === firstLine) - Number(a === firstLine);
        }
        if (a === UNKNOWN_LINE || b === UNKNOWN_LINE) {
            return Number(a === UNKNOWN_LINE) - Number(b === UNKNOWN_LINE);
        }

        return collator.compare(lines.get(a)?.shortName ?? '', lines.get(b)?.shortName ?? '');
    }

    function compareDestinations(a: string, b: string): number {
        if (a === UNKNOWN_DESTINATION || b === UNKNOWN_DESTINATION) {
            return Number(a === UNKNOWN_DESTINATION) - Number(b === UNKNOWN_DESTINATION);
        }

        return collator.compare(a, b);
    }

    function headingOf(key: string): string {
        const line = lines.get(key);

        if (line === undefined) {
            return 'Ligne inconnue';
        }

        return lineLabel(line);
    }

    function lineNameOf(key: string): string {
        const line = lines.get(key);

        return line === undefined ? 'ligne inconnue' : `${line.modeName} ${line.shortName}`;
    }

    function createGroup(key: string): Group {
        const element = template(root, '[data-line-template]');
        const group = { element, heading: required(element, '[data-heading]'), destinations: required(element, '[data-destinations]'), subgroups: new Map() };
        groups.set(key, group);

        return group;
    }

    function createSubgroup(group: Group, destination: string): Subgroup {
        const element = template(root, '[data-destination-template]');
        const subgroup = { element, heading: required(element, '[data-heading]'), list: required(element, '[data-vehicles]') };
        group.subgroups.set(destination, subgroup);

        return subgroup;
    }

    function createRow(vehicle: Vehicle): Row {
        const element = template(root, '[data-vehicle-template]') as HTMLLIElement;
        element.dataset.vehicle = vehicle.id;
        const row = {
            element,
            name: required(element, '[data-name]'),
            detail: required(element, '[data-detail]'),
            age: required(element, '[data-age]') as HTMLTimeElement,
            vehicle,
        };
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

/**
 * Keyboard users reach the buttons with Tab, past the headings: a button names its line and destination, and its number
 * since other vehicles share them, or else its number and its line.
 */
function renderName(row: Row, lineName: string, line: Line | undefined): void {
    const { name, named } = vehicleName(row.vehicle, line);
    const detail = named ? `véhicule ${displayId(row.vehicle)}` : lineName;

    if (row.name.textContent !== name) {
        row.name.textContent = name;
    }
    if (row.detail.textContent !== detail) {
        row.detail.textContent = detail;
    }
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
