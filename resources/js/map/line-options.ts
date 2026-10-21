import { lineLabel, type Line, type LineCatalogue } from './lines';
import type { Vehicle } from './live';

// The selector lists the lines that run now, and the selected one whatever its vehicles (E3.4). The server renders the
// list at load; this keeps it up to date while the page stays open, as vehicles start and stop.

// The order of App\Enums\TransportMode, by which the server groups the lines.
const MODES = ['tram', 'metro', 'rail', 'bus', 'ferry', 'cable_car', 'other'];

export interface LineOptions {
    update(vehicles: Vehicle[], selected: string | null): void;
}

export function showLineOptions(select: HTMLSelectElement, lines: LineCatalogue): LineOptions {
    const collator = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });
    const all = select.querySelector<HTMLOptionElement>('option[value=""]');
    // The lines listed, by short name, as rendered by the server at first.
    let listed = [...select.options]
        .map((option) => option.value)
        .filter((value) => value !== '')
        .join('\n');
    let waiting: Line[] | null = null;

    // Options that change under the focus would move what a keyboard or screen reader user is going through (RGAA).
    select.addEventListener('blur', () => {
        if (waiting !== null) {
            render(waiting);
        }
    });

    function render(shown: Line[]): void {
        const value = select.value;
        const groups = new Map<string, HTMLOptGroupElement>();

        for (const line of shown) {
            let group = groups.get(line.modeName);
            if (group === undefined) {
                group = document.createElement('optgroup');
                group.label = line.modeName;
                groups.set(line.modeName, group);
            }
            group.append(new Option(lineLabel(line), line.shortName));
        }

        select.replaceChildren(...(all === null ? [] : [all]), ...groups.values());
        select.value = value;
        listed = shown.map((line) => line.shortName).join('\n');
        waiting = null;
    }

    return {
        update(vehicles, selected) {
            const running = new Set(vehicles.map((vehicle) => vehicle.lineId));
            const shown = [...lines.all()]
                .filter((line) => running.has(line.id) || line.shortName === selected)
                .sort((a, b) => MODES.indexOf(a.mode) - MODES.indexOf(b.mode) || collator.compare(a.shortName, b.shortName));

            if (shown.map((line) => line.shortName).join('\n') === listed) {
                waiting = null;
            } else if (document.activeElement === select) {
                waiting = shown;
            } else {
                render(shown);
            }
        },
    };
}
