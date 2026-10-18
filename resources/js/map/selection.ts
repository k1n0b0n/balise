// What the user chose (E3.4): a line in the selector, a vehicle on the map or in the text view. Both live in the URL,
// so that a selection can be shared and survives a reload; the history keeps one entry per page, not per choice.

export interface Selection {
    // The line's short name, as people read it: `?ligne=A`.
    line: string | null;
    // The vehicle id without its network prefix, as the popup shows it: `?vehicule=ineo-tram:2543`.
    vehicle: string | null;
}

export function readSelection(): Selection {
    const query = new URLSearchParams(window.location.search);

    return { line: query.get('ligne') || null, vehicle: query.get('vehicule') || null };
}

export function writeSelection(selection: Selection): void {
    const url = new URL(window.location.href);

    for (const [name, value] of [
        ['ligne', selection.line],
        ['vehicule', selection.vehicle],
    ] as const) {
        if (value === null) {
            url.searchParams.delete(name);
        } else {
            url.searchParams.set(name, value);
        }
    }

    if (url.href !== window.location.href) {
        window.history.replaceState(window.history.state, '', url);
    }
}
