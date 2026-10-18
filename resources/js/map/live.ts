// Live store of the map page: asks the server for what changed, every few seconds, while the tab is visible (ADR-0006).

export interface Vehicle {
    id: string;
    latitude: number;
    longitude: number;
    recordedAt: string;
    lineId: string | null;
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

    async function refresh(): Promise<void> {
        const response = await fetch(version === null ? url : `${url}?since=${version}`, {
            headers: version === null ? { Accept: 'application/json' } : { Accept: 'application/json', 'If-None-Match': `"${version}"` },
            // The answer depends on what this page already has: never take it from the HTTP cache.
            cache: 'no-store',
            // A request that never ends would stop the polling: give up and let the next tick try again.
            signal: AbortSignal.timeout(10_000),
        });

        if (!response.ok) {
            return;
        }

        const changes = (await response.json()) as Changes;

        // Paused while the request was on its way: nothing moves, and resuming asks again from the same version.
        if (paused) {
            return;
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
    }

    async function tick(): Promise<void> {
        requesting = true;
        timer = undefined;

        try {
            await refresh();
        } catch {
            // Offline or server unreachable: the next tick tries again.
        }

        requesting = false;

        if (!document.hidden && !paused) {
            timer = window.setTimeout(tick, everySeconds * 1000);
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
