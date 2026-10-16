# Product requirements

> Status: draft. Acceptance criteria to be written together before any story starts.

## Non-functional requirements

Apply to every story ([ADR-0006](adr/0006-responsible-design.md)).

- **Accessibility:** RGAA 4.1.2, level AA. No axe violation on any page (from E3.1); the relevant RGAA criteria are checked during review.
- **Ecodesign:** the applicable RGESN 2024 criteria are checked during review; the map page stays within its published weight and EcoIndex budget.

## Milestones

| Milestone | Epics | Meaning |
|---|---|---|
| **M1: Visible** | E1, E2, E3 (without real-time push) | Public URL shows live vehicles, CI green, automated deploy |
| **M2: Real-time** | E4, E5 | Positions pushed over WebSockets, replay mode, measured load |
| **M3: Multi-network** | E6 | Second network added by configuration |

## Epics and stories

### E1: Foundation and delivery
- E1.1 Laravel + Livewire skeleton with quality gates (Pest, Larastan, Pint, architecture tests)
  - [x] Fresh Laravel (latest stable) with Livewire, PHP 8.5, PostgreSQL as the only database (ADR-0002)
  - [x] `docker compose up` starts every service of ADR-0007; the home page answers 200 and renders a Livewire component
  - [x] `composer qa` runs `pint --test`, Larastan and Pest, and exits non-zero if any of them fails (ADR-0005)
  - [x] Larastan passes at level max on `app/` with no baseline file
  - [x] Architecture tests: strict types everywhere, no `dd`/`dump`/`var_dump`/`ray`, and no class outside `App\Ingestion` referencing a GTFS, GTFS-RT, protobuf or SIRI type (ADR-0001); the boundary rule only bites once E2.2 installs the feed packages
  - [x] Tests run against PostgreSQL, not SQLite
  - [x] README and AGENTS.md list the commands: start, `composer qa`, run a single test
- E1.2 CI on GitHub Actions: `composer qa`, dependency audit, Conventional Commits check on pull request titles, Dependabot (ADR-0004, ADR-0005)
  - [x] A workflow runs on every pull request and on every push to `main`; its job `ci` runs `composer qa` against PostgreSQL 18, with the PHP version and extensions of `docker/php/packages.txt` installed by `setup-php` (faster than building the image; server parity is checked in E1.3)
  - [x] The same job builds the front end (`npm ci`, `npm run build`)
  - [x] `composer audit` and `npm audit --audit-level=high` fail the job on a known vulnerability
  - [x] Pull request titles are checked against Conventional Commits, with the types release-please understands
  - [x] Dependabot opens weekly, grouped updates for Composer, npm and GitHub Actions, with Conventional Commits titles; Docker images follow the server instead (E1.3)
  - [x] Workflows use least-privilege `permissions` and actions pinned to a commit SHA
  - [x] The ruleset requires both checks; a pull request with a failing test cannot be merged, shown once on a throwaway pull request
- E1.3 Server provisioned as code (Oracle Cloud, OpenTofu, Ansible), deploy on GitHub release created by release-please (ADR-0004, ADR-0012)
  - [ ] `tofu apply` creates the instance, network and firewall; state is stored remotely and encrypted
  - [ ] An Ansible playbook configures the server from the repository: nginx site, `php.ini`, php-fpm pool, PHP packages and daemon commands (ADR-0007)
  - [ ] One file in the repository holds the versions of Ubuntu, PHP, PostgreSQL, Redis and Node; Ansible installs those versions, and a required `versions` check fails a pull request when the Dockerfiles, `compose.yaml` or the CI workflow disagree with it
  - [ ] Publishing a release deploys it: build on the runner, upload, migrate, switch the `current` symlink, reload; the site answers 200 afterwards
  - [ ] A weekly workflow runs the playbook in check mode against the server and opens or updates a drift issue; it never blocks pull requests
  - [ ] A weekly workflow opens a pull request raising one version by one step when a newer supported one exists (Ubuntu LTS, PHP, PostgreSQL, Redis, Node LTS)
  - [ ] The server is destroyed and rebuilt once from the repository, data reloaded by the GTFS import and the seeders; the time it takes is recorded
  - [ ] Other criteria to be written
- E1.4 Cloudflare in front (DNS, proxy, cache rules)
- E1.5 Public accessibility statement and ecodesign statement, linked from every page (ADR-0006)

### E2: Ingestion
- E2.1 Import the static GTFS (routes, stops, colours)
  - [ ] `php artisan network:import {network}` imports lines (namespaced id, short name, long name, mode, colour, text colour) and stops (namespaced id, name, coordinates) through the `gtfs` driver of `NetworkCatalog`; colours are optional (73 of 202 TBM lines have none)
  - [ ] Only the files needed are read from the archive, streamed: memory stays flat whatever the size of `stop_times.txt` (21 MB archive for TBM)
  - [ ] The import runs in one transaction and is idempotent: running it twice leaves the same data, a line or stop removed from the feed is removed
  - [ ] The download sends `If-None-Match` with the last ETag and skips the import on `304` (RGESN)
  - [ ] The scheduler runs the import daily, after the provider's nightly update
  - [ ] Tests run against a small GTFS archive in `tests/fixtures`
- E2.2 Poll the GTFS-RT VehiclePositions feed on a schedule
  - [ ] The protobuf / GTFS-RT library is chosen in an ADR before the driver is written
  - [ ] The scheduler polls each network at its configured interval, 20 s for TBM (the feed changes every 20 s, measured 2026-10-16), without overlapping runs
  - [ ] Each request sends `If-None-Match`; a `304` ends the run without parsing (RGESN)
  - [ ] The `gtfs-rt` driver maps the feed to ADR-0013 positions, with each fallback covered by a test on a recorded payload
  - [ ] A failed or invalid fetch is logged and leaves the current state untouched; the next run retries
  - [ ] The architecture test fails on a deliberate GTFS-RT reference outside `App\Ingestion`, shown once and reverted (story 001 known limit)
- E2.3 Filter stale positions and keep the latest state per vehicle (ADR-0013)
  - [ ] Positions more than 5 min older than their reception, or more than 2 min ahead of it, are dropped; both limits are per-network settings, and the number dropped is logged per run
  - [ ] The single write path upserts the latest position per vehicle and emits `PositionsUpdated` with the changed positions only; an unchanged poll emits nothing
  - [ ] A vehicle whose last position is older than the limit leaves the current state
  - [ ] One TBM poll (about 400 vehicles) is processed in under one second on the production server, measured

### E3: Live map
- E3.1 MapLibre map inside Livewire, open basemap without API key; Tailwind installed (ADR-0008)
- E3.2 Vehicles as points coloured and labelled by line, with clustering; line colours checked for contrast; vehicles without a line shown as "Unknown line" (ADR-0006, ADR-0013)
- E3.3 Vehicle popup: line, vehicle id, age of last position
- E3.4 Select a line or a vehicle and let the map follow it
- E3.5 Accessible text view equivalent to the map: lines, their vehicles and the age of the last position (ADR-0006)
- E3.6 Pause and resume live updates (ADR-0006)

### E4: Real-time push
- E4.1 Broadcast position updates with Reverb
- E4.2 Fallback to periodic refresh when the socket drops

### E5: Replay and load
- E5.1 Record raw feed payloads
- E5.2 Replay feed, selectable in configuration
- E5.3 Load test (k6) against cached endpoints, results published in the README

### E6: Second network
- E6.1 Add a second French network through configuration only

## Later

- Position history, stage 2: short window (24–48 h), e.g. a vehicle's recent trail (ADR-0002)
- Position history, stage 3: long retention for statistics such as delays per line (ADR-0002)
- First spatial feature (stops and vehicles around me, viewport filtering), bringing PostGIS (ADR-0002)
- SIRI-VM and NeTEx drivers, when a network needs them (ADR-0001)
- Next departures at a stop: static GTFS stop times corrected by GTFS-RT TripUpdates, behind the anticipated trip updates port (ADR-0001). Out of the MVP scope (brief: no timetables)
- Brand web font, chosen with a visual identity; system fonts until then (ADR-0008)
- `laravel/pail` to filter live logs, once ingestion produces logs worth filtering (ADR-0007)
- Cross-check vehicle positions with GTFS-RT TripUpdates to give lineless vehicles their trip and line (ADR-0013)
- Bearing arrows and destination labels on vehicles (ADR-0013)
- `SECURITY.md` with private vulnerability reporting, pull request template mirroring the definition of done
