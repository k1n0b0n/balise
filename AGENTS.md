# balise — agent instructions

## Project

Live map of public transport vehicles fed by open GTFS-Realtime data. First network: TBM (Bordeaux). Audience: technical reviewers reading the repo in about ten minutes, so readability beats cleverness.

Stack: Laravel 13, Livewire 4, PHP 8.5, PostgreSQL 17. Tests: Pest (feature tests in `tests/Feature`, architecture tests in `tests/Unit/ArchitectureTest.php`), run against the `testing` PostgreSQL database.

## Commands

Everything runs in Docker (ADR-0007); no PHP is needed on the host.

| Task | Command |
|---|---|
| Start the stack (first run installs dependencies, creates `.env`, migrates) | `docker compose up -d` |
| Open the app | http://localhost:8000 (`APP_PORT` to change) |
| All quality gates: Pint, Larastan, Pest | `docker compose exec php composer qa` |
| Fix code style | `docker compose exec php vendor/bin/pint` |
| One test file / one test | `docker compose exec php vendor/bin/pest tests/Feature/HomeTest.php` / `--filter "renders the home page"` |
| Artisan | `docker compose exec php php artisan <command>` |
| Stop / reset the database | `docker compose down` / `docker compose down -v` |

Pest architecture rules ignore classes that cannot be autoloaded: the feed-format boundary rule only bites once the protobuf/GTFS-RT packages are installed.

## Rules

- Start every session by reading the *Current state* section of `docs/README.md`, and update it before ending.
- Follow the BMAD-lite workflow in `docs/README.md`. Work on one story at a time, from its file in `docs/stories/` (copy `_template.md`).
- Never implement anything that is not in `docs/prd.md`. Propose it for the *Later* section instead.
- A decision that is costly to reverse (new dependency, boundary, data store, hosting) needs an ADR in `docs/adr/` before code.
- All code, docs, comments and commit messages in English. Conventional Commits.
- A story is done only when tests, static analysis and architecture tests pass, the non-functional requirements of the PRD (RGAA, RGESN) are met, and the diff has been reviewed by a human.

## Architecture (from `docs/architecture.md` and the ADRs)

- **Ingestion boundary (ADR-0001).** Two app-owned ports: `VehicleFeed` (live positions) and `NetworkCatalog` (lines, stops, shapes). One adapter per *standard*, not per network: `gtfs-rt`, `gtfs`, `replay` for the MVP; `siri-vm`, `netex` only when a network needs them. Networks pick drivers through configuration (URLs, timezone, id prefix).
- **Only the ingestion layer knows about GTFS, GTFS-RT, SIRI, protobuf or any feed format.** Architecture tests enforce it. Everything outside is plain Laravel; no hexagonal/DDD ceremony elsewhere.
- **Normalised model** (network, line, stop, vehicle, position): GTFS-inspired but owned by the app, ids namespaced per network, fields that some standards lack are optional. Never mirror GTFS tables as-is. Exact position fields are still an open question.
- **Single write path (ADR-0002).** Every position write goes through one place that emits `PositionsUpdated`. History, broadcasting (Reverb) and future features are listeners on that event.
- **Storage (ADR-0002, Accepted).** PostgreSQL; coordinates as plain `latitude`/`longitude` until the first spatial story brings PostGIS (own ADR, migration to `geography(Point, 4326)`). M1 keeps only the latest position per vehicle; latest positions are hot data for Redis/memory, the DB is the system of record. Local dev uses a PostgreSQL Alpine image.
- **Replay** records raw feed payloads and plays them back through the real driver; it does not depend on position history.
- **UI.** Livewire + MapLibre GL JS; the map is a JS island isolated from Livewire re-renders. RGAA AA and RGESN apply (ADR-0006): the map has an equivalent text view and a pause control, and only changed positions are pushed.
- **Local environment (ADR-0007).** `compose.yaml`, one process per service; PHP and nginx images on Ubuntu 24.04 like Forge. Application-facing config (nginx, `php.ini`, php-fpm pool) is versioned under `docker/` and applied to Forge too.

## Data facts

- TBM feed: ~424 vehicles at midday; some positions are up to ~3.5 h old and must be filtered (E2.3). The network is nearly empty at night, hence replay mode.
- Source: transport.data.gouv.fr, Licence Ouverte.
