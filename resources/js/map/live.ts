// Live store of the map page: asks the server for what changed while the tab is visible (ADR-0006), just after the server
// expects new positions, or every few seconds when it cannot say (E4.1).

export interface Vehicle {
    id: string;
    latitude: number;
    longitude: number;
    recordedAt: string;
    lineId: string | null;
    destination: string | null;
}

/**
 * The vehicle id as the feed gave it: ids are namespaced by network (`tbm:1234`), only the prefix goes.
 */
export function displayId(vehicle: Vehicle): string {
    return vehicle.id.slice(vehicle.id.indexOf(':') + 1);
}

interface Changes {
    version: number;
    full: boolean;
    vehicles: Vehicle[];
    removed: string[];
}

/**
 * Milliseconds until the server expects its next version (E4.1), counted on the server's clock: its `Date`, which a
 * cached answer also gets fresh, against the time it names. Null when it says nothing, when the time has passed, or when
 * it is further than a minute away, which no feed's interval is.
 */
export function untilNextVersion(response: Response): number | null {
    const next = Number(response.headers.get('Next-Version-At') ?? Number.NaN);
    const now = Date.parse(response.headers.get('Date') ?? '');

    if (!Number.isFinite(next) || Number.isNaN(now) || next * 1000 <= now || next * 1000 - now > 60_000) {
        return null;
    }

    return next * 1000 - now;
}

// A page asking at the same moment as every other one would make the server answer them all at once: spread them.
const SPREAD_MILLISECONDS = 1000;

export interface LiveControl {
    pause(): void;
    resume(): void;
}

export function followVehicles(url: string, everySeconds: number, onChange: (vehicles: Vehicle[]) => void): LiveControl {
    const vehicles = new Map<string, Vehicle>();
    let version: number | null = null;
    let timer: number | undefined;
    let requesting = false;
    // Paused by the user (E3.6): no request at all until resumed.
    let paused = false;
    // This request was sent when the server expected a new version.
    let aligned = false;

    /**
     * Asks for what changed; says how long to wait before the next request.
     */
    async function refresh(): Promise<number> {
        const response = await fetch(version === null ? url : `${url}?since=${version}`, {
            headers: version === null ? { Accept: 'application/json' } : { Accept: 'application/json', 'If-None-Match': `"${version}"` },
            // The answer depends on what this page already has: never take it from the HTTP cache.
            cache: 'no-store',
            // A request that never ends would stop the polling: give up and let the next tick try again.
            signal: AbortSignal.timeout(10_000),
        });

        const wait = untilNextVersion(response);
        // The version expected has not come yet (the provider is late): ask again at the usual interval, not a whole
        // feed interval later.
        const late = aligned && response.status === 304;
        aligned = wait !== null && !late;
        const next = aligned && wait !== null ? wait + Math.random() * SPREAD_MILLISECONDS : everySeconds * 1000;

        if (!response.ok) {
            return next;
        }

        const changes = (await response.json()) as Changes;

        // Paused while the request was on its way: nothing moves, and resuming asks again from the same version.
        if (paused) {
            return next;
        }

        if (changes.full) {
            vehicles.clear();
        }
        for (const id of changes.removed) {
            vehicles.delete(id);
        }
        for (const vehicle of changes.vehicles) {
            vehicles.set(vehicle.id, vehicle);
        }

        version = changes.version;
        onChange([...vehicles.values()]);

        return next;
    }

    async function tick(): Promise<void> {
        requesting = true;
        timer = undefined;
        let next = everySeconds * 1000;

        try {
            next = await refresh();
        } catch {
            // Offline or server unreachable: the next tick tries again at the usual interval.
            aligned = false;
        }

        requesting = false;

        if (!document.hidden && !paused) {
            timer = window.setTimeout(tick, next);
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            window.clearTimeout(timer);
            timer = undefined;
        } else if (timer === undefined && !requesting && !paused) {
            void tick();
        }
    });

    void tick();

    return {
        pause() {
            paused = true;
            window.clearTimeout(timer);
            timer = undefined;
        },
        // One request catches up with every change since the last version, then the interval resumes.
        resume() {
            paused = false;

            if (timer === undefined && !requesting && !document.hidden) {
                void tick();
            }
        },
    };
}
