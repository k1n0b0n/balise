// Line catalogue: what each line looks like, fetched once instead of with every position (E3.5, RGESN).

// A line still missing after a refetch is looked for again at most once an hour: the daily import may bring it.
const SEARCH_AGAIN_MS = 60 * 60 * 1000;

export interface Line {
    id: string;
    shortName: string;
    longName: string | null;
    mode: string;
    modeName: string;
    colour: string;
}

export class LineCatalogue {
    private lines = new Map<string, Line>();
    private loaded = false;
    // True once the first fetch has answered or failed: drawing earlier would show every vehicle without its line.
    settled = false;
    private loading = false;
    // While live updates are paused (E3.6), an answer is kept but not shown, and a failed load is retried on resume.
    private paused = false;
    private changedWhilePaused = false;
    private retryOnResume = false;
    private retry: number | undefined;
    // When each missing line was last looked for: a line the import does not know is not asked for on every update.
    private readonly searched = new Map<string, number>();

    constructor(
        private readonly url: string,
        private readonly retrySeconds: number,
        private readonly onChange: () => void,
    ) {}

    get(id: string | null): Line | undefined {
        return id === null ? undefined : this.lines.get(id);
    }

    /**
     * Loads the catalogue, or fetches it again when positions name lines it lacks (a newer daily import), at most once
     * an hour for the same line.
     */
    ensure(lineIds: Iterable<string | null>): void {
        if (!this.loaded) {
            void this.load();

            return;
        }

        const now = Date.now();
        const missing = [...new Set(lineIds)].filter(
            (id): id is string => id !== null && !this.lines.has(id) && now - (this.searched.get(id) ?? -Infinity) >= SEARCH_AGAIN_MS,
        );

        if (missing.length > 0) {
            missing.forEach((id) => this.searched.set(id, now));
            void this.load();
        }
    }

    pause(): void {
        this.paused = true;

        if (this.retry !== undefined) {
            window.clearTimeout(this.retry);
            this.retry = undefined;
            this.retryOnResume = true;
        }
    }

    resume(): void {
        this.paused = false;

        if (this.changedWhilePaused) {
            this.changedWhilePaused = false;
            this.onChange();
        }
        if (this.retryOnResume) {
            this.retryOnResume = false;
            void this.load();
        }
    }

    private async load(): Promise<void> {
        if (this.loading) {
            return;
        }
        this.loading = true;

        try {
            // The server answers `no-cache` with an ETag: the browser revalidates and gets a 304 when nothing changed.
            const response = await fetch(this.url, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10_000) });

            if (!response.ok) {
                throw new Error(`Line catalogue: HTTP ${response.status}`);
            }

            const { lines } = (await response.json()) as { lines: Line[] };
            this.lines = new Map(lines.map((line) => [line.id, line]));
            this.loaded = true;
            this.settled = true;
            this.changed();
        } catch {
            // Until it loads, vehicles show like vehicles without a line.
            if (!this.settled) {
                this.settled = true;
                this.changed();
            }
            if (this.paused) {
                this.retryOnResume = true;

                return;
            }
            this.retry ??= window.setTimeout(() => {
                this.retry = undefined;
                void this.load();
            }, this.retrySeconds * 1000);
        } finally {
            this.loading = false;
        }
    }

    private changed(): void {
        if (this.paused) {
            this.changedWhilePaused = true;
        } else {
            this.onChange();
        }
    }
}
