/**
 * A station's next departures (E8.2), as `/api/networks/{network}/stations/{station}/departures` sends them.
 */
export interface DepartureBoard {
    available: boolean;
    groups: {
        line: { label: string; colour: string } | null;
        destination: string | null;
        // Seconds since the epoch: worded at the time they are shown.
        times: number[];
    }[];
}

export function departuresUrl(stationsUrl: string, stationId: string): string {
    return `${stationsUrl}/${encodeURIComponent(stationId)}/departures`;
}

export async function fetchDepartures(url: string): Promise<DepartureBoard | null> {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        return response.ok ? ((await response.json()) as DepartureBoard) : null;
    } catch {
        return null;
    }
}

/**
 * Renders the departures grouped by line then destination, like the text view (E3.7); null is a failed request.
 */
export function renderDepartures(container: HTMLElement, board: DepartureBoard | null, now: Date, timeZone: string): void {
    const clock = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone });

    if (board === null || !board.available) {
        container.replaceChildren(paragraph(board === null ? 'Les prochains passages n’ont pas pu être chargés.' : 'Pas de données temps réel.'));

        return;
    }
    if (board.groups.length === 0) {
        container.replaceChildren(paragraph('Aucun départ dans les deux prochaines heures.'));

        return;
    }

    const list = document.createElement('ul');
    list.className = 'mt-1 space-y-1';
    for (const group of board.groups) {
        const item = document.createElement('li');
        item.className = 'border-l-4 pl-2';
        item.style.borderColor = group.line?.colour ?? 'currentColor';
        const line = Object.assign(document.createElement('span'), { className: 'font-semibold', textContent: group.line?.label ?? 'Ligne inconnue' });
        const times = Object.assign(document.createElement('span'), {
            className: 'block text-sm',
            textContent: group.times.map((time) => when(time, now, clock)).join(', '),
        });
        item.append(line, ` vers ${group.destination ?? 'destination inconnue'}`, times);
        list.append(item);
    }
    container.replaceChildren(list);
}

/**
 * "dans 3 min" within the hour, the time beyond it.
 */
function when(time: number, now: Date, clock: Intl.DateTimeFormat): string {
    const minutes = Math.floor((time * 1000 - now.getTime()) / 60_000);

    if (minutes < 1) {
        return 'à l’approche';
    }

    return minutes < 60 ? `dans ${minutes} min` : `à ${clock.format(new Date(time * 1000))}`;
}

function paragraph(text: string): HTMLParagraphElement {
    return Object.assign(document.createElement('p'), { className: 'text-sm', textContent: text });
}
