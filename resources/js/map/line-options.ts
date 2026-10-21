import { lineLabel, type Line, type LineCatalogue } from './lines';
import type { Vehicle } from './live';

// The selectors list the modes and the lines that run now, the line selector narrowed to the chosen mode, and the
// selected line whatever its vehicles (E3.4). The server renders them at load; this keeps them up to date while the
// page stays open, as vehicles start and stop, and applies a mode at once.

// The order of App\Enums\TransportMode, by which the server groups the lines.
const MODES = ['tram', 'metro', 'rail', 'bus', 'ferry', 'cable_car', 'other'];

export interface LineOptions {
    update(vehicles: Vehicle[], selected: string | null): void;
}

interface Choices {
    // The select's first option, "all", kept as rendered by the server.
    all: HTMLOptionElement | null;
    // The values listed, as rendered by the server at first.
    listed: string;
    waiting: (() => void) | null;
}

export function showLineOptions(lineSelect: HTMLSelectElement, modeSelect: HTMLSelectElement | null, lines: LineCatalogue): LineOptions {
    const collator = new Intl.Collator('fr', { numeric: true, sensitivity: 'base' });
    const choices = new Map<HTMLSelectElement, Choices>();
    let vehicles: Vehicle[] = [];
    let selected: string | null = null;

    for (const select of [lineSelect, modeSelect]) {
        if (select === null) {
            continue;
        }
        const state: Choices = {
            all: select.querySelector<HTMLOptionElement>('option[value=""]'),
            listed: listing(select),
            waiting: null,
        };
        choices.set(select, state);
        // Options that change under the focus would move what a keyboard or screen reader user is going through (RGAA).
        select.addEventListener('blur', () => state.waiting?.());
    }

    // A mode narrows the lines at once: the focus is on the mode selector, not on the lines.
    modeSelect?.addEventListener('change', () => refresh());

    function listing(select: HTMLSelectElement): string {
        return [...select.options]
            .map((option) => option.value)
            .filter((value) => value !== '')
            .join('\n');
    }

    // Rebuilds a select's options when they differ from those listed, once it has lost the focus.
    function replace(select: HTMLSelectElement, values: string[], build: () => (HTMLOptionElement | HTMLOptGroupElement)[]): void {
        const state = choices.get(select);
        if (state === undefined) {
            return;
        }
        const render = (): void => {
            const value = select.value;
            select.replaceChildren(...(state.all === null ? [] : [state.all]), ...build());
            select.value = value;
            state.listed = values.join('\n');
            state.waiting = null;
        };

        if (values.join('\n') === state.listed) {
            state.waiting = null;
        } else if (document.activeElement === select) {
            state.waiting = render;
        } else {
            render();
        }
    }

    function refresh(): void {
        const running = new Set(vehicles.map((vehicle) => vehicle.lineId));
        const shown = [...lines.all()]
            .filter((line) => running.has(line.id) || line.shortName === selected)
            .sort((a, b) => MODES.indexOf(a.mode) - MODES.indexOf(b.mode) || collator.compare(a.shortName, b.shortName));

        if (modeSelect !== null) {
            // The chosen mode stays listed while no line of it runs, as the selected line does.
            const modes = new Map<string, string>(shown.map((line) => [line.mode, line.modeName]));
            const current = modeSelect.selectedOptions[0];
            if (current !== undefined && current.value !== '' && !modes.has(current.value)) {
                modes.set(current.value, current.text);
            }
            const ordered = [...modes].sort(([a], [b]) => MODES.indexOf(a) - MODES.indexOf(b));
            replace(
                modeSelect,
                ordered.map(([mode]) => mode),
                () => ordered.map(([mode, name]) => new Option(name, mode)),
            );
        }

        const mode = modeSelect?.value ?? '';
        const listed = shown.filter((line) => mode === '' || line.mode === mode || line.shortName === selected);
        replace(
            lineSelect,
            listed.map((line) => line.shortName),
            () => group(listed),
        );
    }

    function group(listed: Line[]): HTMLOptGroupElement[] {
        const groups = new Map<string, HTMLOptGroupElement>();

        for (const line of listed) {
            let optgroup = groups.get(line.modeName);
            if (optgroup === undefined) {
                optgroup = document.createElement('optgroup');
                optgroup.label = line.modeName;
                groups.set(line.modeName, optgroup);
            }
            optgroup.append(new Option(lineLabel(line), line.shortName));
        }

        return [...groups.values()];
    }

    return {
        update(next, line) {
            vehicles = next;
            selected = line;
            refresh();
        },
    };
}
