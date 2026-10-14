# balise — agent instructions

## Project

Live map of public transport vehicles fed by open GTFS-Realtime data. First network: TBM (Bordeaux). Audience: technical reviewers reading the repo in about ten minutes, so readability beats cleverness.

Status: design phase, no application code yet. There are no build, lint or test commands. Story E1.1 creates them (Laravel + Livewire, Pest, PHPStan, Pint, architecture tests); add them to this file when it lands.

## Rules

- Start every session by reading the *Current state* section of `docs/README.md`, and update it before ending.
- Follow the BMAD-lite workflow in `docs/README.md`. Work on one story at a time, from its file in `docs/stories/` (copy `_template.md`).
- Never implement anything that is not in `docs/prd.md`. Propose it for the *Later* section instead.
- A decision that is costly to reverse (new dependency, boundary, data store, hosting) needs an ADR in `docs/adr/` before code.
- All code, docs, comments and commit messages in English. Conventional Commits.
- A story is done only when tests, static analysis and architecture tests pass and the diff has been reviewed by a human.

## Architecture (from `docs/architecture.md` and the ADRs)

- **Ingestion boundary (ADR-0001, Proposed).** Two app-owned ports: `VehicleFeed` (live positions) and `NetworkCatalog` (lines, stops, shapes). One adapter per *standard*, not per network: `gtfs-rt`, `gtfs`, `replay` for the MVP; `siri-vm`, `netex` only when a network needs them. Networks pick drivers through configuration (URLs, timezone, id prefix).
- **Only the ingestion layer knows about GTFS, GTFS-RT, SIRI, protobuf or any feed format.** Architecture tests enforce it. Everything outside is plain Laravel; no hexagonal/DDD ceremony elsewhere.
- **Normalised model** (network, line, stop, vehicle, position): GTFS-inspired but owned by the app, ids namespaced per network, fields that some standards lack are optional. Never mirror GTFS tables as-is. Exact position fields are still an open question.
- **Single write path (ADR-0002).** Every position write goes through one place that emits `PositionsUpdated`. History, broadcasting (Reverb) and future features are listeners on that event.
- **Storage (ADR-0002, Accepted).** PostgreSQL; coordinates as plain `latitude`/`longitude` until the first spatial story brings PostGIS (own ADR, migration to `geography(Point, 4326)`). M1 keeps only the latest position per vehicle; latest positions are hot data for Redis/memory, the DB is the system of record. Local dev uses a PostgreSQL Alpine image.
- **Replay** records raw feed payloads and plays them back through the real driver; it does not depend on position history.
- **UI.** Livewire + MapLibre GL JS; the map is a JS island isolated from Livewire re-renders.

## Data facts

- TBM feed: ~424 vehicles at midday; some positions are up to ~3.5 h old and must be filtered (E2.3). The network is nearly empty at night, hence replay mode.
- Source: transport.data.gouv.fr, Licence Ouverte.
