// Line catalogue: what each line looks like, fetched once instead of with every position (E3.5, RGESN).

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
    private retry: number | undefined;
    // Lines already looked for in a refetch: a line the import does not know is not asked for on every update.
    private readonly searched = new Set<string>();

    constructor(
        private readonly url: string,
        private readonly retrySeconds: number,
        private readonly onChange: () => void,
    ) {}

    get(id: string | null): Line | undefined {
        return id === null ? undefined : this.lines.get(id);
    }

    /**
     * Loads the catalogue, or fetches it again once when positions name lines it lacks (a newer daily import).
     */
    ensure(lineIds: Iterable<string | null>): void {
        if (!this.loaded) {
            void this.load();

            return;
        }

        const missing = [...new Set(lineIds)].filter((id): id is string => id !== null && !this.lines.has(id) && !this.searched.has(id));

        if (missing.length > 0) {
            missing.forEach((id) => this.searched.add(id));
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
            this.onChange();
        } catch {
            // Until it loads, vehicles show like vehicles without a line.
            if (!this.settled) {
                this.settled = true;
                this.onChange();
            }
            this.retry ??= window.setTimeout(() => {
                this.retry = undefined;
                void this.load();
            }, this.retrySeconds * 1000);
        } finally {
            this.loading = false;
        }
    }
}
