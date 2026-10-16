# ADR-0011: Map library and basemap, MapLibre GL JS with OpenFreeMap

- **Status:** Accepted
- **Date:** 2026-10-15
- **Amended:** 2026-10-16, during E3.2: no clustering; vehicles are small markers shaped by mode with the line's short name beside them, all MapLibre style expressions and canvas-drawn images (story 007). Where this ADR mentions clustering, it no longer applies

## Context

- The map shows about 400 vehicles, coloured and labelled by line, with clustering (E3.2), a popup (E3.3) and a followed selection (E3.4).
- The project demonstrates MapLibre for a job posting that names it explicitly (brief: technical reviewers).
- The basemap must work without an API key (E3.1) and withstand a public demo.
- RGAA AA and RGESN apply (ADR-0006); the map has an equivalent text view (E3.5) whatever the library.
- `architecture.md` listed MapLibre as "agreed" and OpenFreeMap as "to confirm", without an ADR.

### Measured weights (minified + gzip, 2026-10-15)

| Library | Size |
|---|---|
| MapLibre GL JS 6.14 (main + worker) | ~430 KB |
| OpenLayers 10.11 (vector tiles + clustering only) | ~102 KB |
| Leaflet 1.9.4 (with its CSS) | ~46 KB |

## Decision

1. **MapLibre GL JS** renders the map, with vehicles in a GeoJSON source using its built-in clustering.
2. **OpenFreeMap** vector tiles and style as the basemap: no key, no account. If it becomes unreliable, the fallback is a self-hosted Protomaps PMTiles extract of the Bordeaux area behind Cloudflare.
3. **The map bundle loads only on the map page**, as its own Vite entry; the statement pages ship no map JavaScript. Its weight counts in the map page budget (ADR-0006).
4. **Accessibility does not rely on the canvas:** vehicles drawn by WebGL cannot take keyboard focus, so the text view (E3.5) is the accessible path, and map controls keep MapLibre's labelled buttons.

## Alternatives considered

| Option | Why not |
|---|---|
| Leaflet | About ten times lighter, and DOM markers can take keyboard focus. But free raster tiles do not allow a public app (OpenStreetMap tile usage policy), so a provider with a key is needed; vector tiles need a plugin that embeds MapLibre anyway; clustering is a plugin. Strongest alternative |
| OpenLayers | Lighter than MapLibre and very capable, but a verbose GIS-oriented API, and matching the OpenFreeMap style needs an extra package. Not the library the project is meant to show |
| Mapbox GL JS | Same API family, but proprietary licence, mandatory token, billed per load |
| Google Maps JavaScript API | Proprietary, API key, user data sent to Google (GDPR), no control over the style |
| deck.gl, CesiumJS | A WebGL data layer and a 3D globe: out of proportion for 400 points on a city map |

## Consequences

- The map page carries about 430 KB of JavaScript for the map alone; the RGESN budget is set with that floor in mind.
- No key, no account, no billing for the map or the basemap.
- Clustering, line colours and labels are style expressions in the map module (`resources/js/map/`, ADR-0010), not DOM code.
- OpenFreeMap is a third-party dependency at runtime; its availability is watched, and the PMTiles fallback is its own story if needed.
