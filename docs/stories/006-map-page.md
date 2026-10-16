# Story 006: MapLibre map page

- **PRD:** E3.1
- **Status:** Review

## Acceptance criteria

See E3.1 in [prd.md](../prd.md).

## Tasks

- [x] `maplibre-gl` (ADR-0011), Tailwind CSS v4 through its Vite plugin (ADR-0008), TypeScript with `npm run typecheck` in CI (ADR-0010)
- [x] Map module `resources/js/map/` (no Livewire or Alpine import) and page entry `resources/js/map.ts`, the only entry that loads MapLibre
- [x] Home page in French: OpenFreeMap Positron style (`config/map.php`), fitted to the network's stops, French control labels, skip link to the text view (placeholder until E3.5)
- [x] `npm run budget`: compressed JavaScript of the map page, checked in CI against 600 KB
- [x] Pest browser tests with Playwright: basemap rendered, `lang="fr"`, labelled canvas and controls, skip link, no axe violation at any impact level

## Notes

- **Two bugs found by measuring, both would have reached production:** MapLibre 6 loads its worker from a separate `.mjs` file, which Ubuntu's nginx served as `application/octet-stream`; with `nosniff`, the browser refused it and no tile loaded, without any uncaught error. `docker/nginx/site.conf` now serves `.mjs` as JavaScript. The same file now compresses CSS and JavaScript (Ubuntu's `nginx.conf` only compresses HTML) and caches hashed assets for a year.
- The browser test now waits for `data-state="loaded"`, set by the map module once the basemap is drawn: the first version passed while the map was blank.
- JavaScript budget: 495.8 KB compressed (MapLibre 270.6, its worker 141.1, Livewire 84.1).
- EcoIndex A (86/100), measured locally with Playwright and `ecoindex-compute` 5.10: 44 DOM nodes, 21 requests, 1,219 KB, of which 713 KB of basemap tiles. Not measured in CI: it needs the running stack and the tile server.
- `pest-plugin-browser` checks axe at "serious" by default; the test uses level 3 so that minor violations fail too.
- Browser tests run on built assets (`npm run build`); with the Vite dev server running, they ignore its hot file.
- The PHP image grows to about 2.3 GB with Node and Chromium (ADR-0007 amended). Playwright is pinned to an exact version: a Dependabot bump needs `docker compose build`.
- pao reports one empty "warning" for the browser suite; the plain Pest output shows none.
- Logs go to the `null` channel in tests (`phpunit.xml`), since compose sets `LOG_CHANNEL=stderr`.
