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
- E1.3 Server prepared from the repository, deploy on GitHub release created by release-please (ADR-0004, ADR-0020); split so that what needs no server comes first
- E1.3a Platform versions in one file
  - [x] One file in the repository holds the versions of Ubuntu, PHP, PostgreSQL, Redis and Node; a required `versions` check fails a pull request when the Dockerfiles, `compose.yaml` or the CI workflow disagree with it
- E1.3b Server configured by Ansible from the repository, proven on a local Ubuntu virtual machine
  - [x] An Ansible playbook configures the server from the repository: nginx site, `php.ini`, php-fpm pool, PHP packages and daemon commands (ADR-0007), with the versions of E1.3a
  - [x] A deploy playbook installs a build: upload, migrate, switch the `current` symlink, reload; the site answers 200 afterwards
  - [x] Ansible runs in a container with pinned versions; nothing to install on the host but Docker (ADR-0019)
  - [x] A second run of the server playbook changes nothing, and check mode reports no drift
  - [x] The build is an archive made outside the server; the server has no Node, Composer or Git (ADR-0004)
  - [x] The previous release stays on disk: a rollback is one symlink switch, tried once
  - [x] `APP_KEY` and the database password are generated on the server once and never leave it; no secret is committed (ADR-0019)
  - [x] The server's own concerns are configured too: a firewall letting only SSH, HTTP and HTTPS in, and security updates installed daily by unattended upgrades
  - [x] On the local machine the scheduler polls TBM and the map shows live vehicles; one poll is timed
- E1.3c Releases, without a server
  - [x] Merging to `main` keeps a release pull request up to date with release-please; merging it creates the tag, the GitHub release and the changelog; it runs with the token of a dedicated GitHub App, so that CI runs on its pull requests (ADR-0004)
- E1.3d Docker deploy on any Debian 13 or Ubuntu 26.04 host, proven on local machines (ADR-0020)
  - [x] The images start from the official `php:8.5-fpm-alpine` and `nginx:alpine` instead of `ubuntu:26.04`, the browser tests' from `php:8.5-cli-trixie`; every quality gate passes on them
  - [x] CI runs the quality gates in the `test` image; each version is written in one place (PHP and Node in the Dockerfile, PostgreSQL and Redis in `compose.yaml`), so `versions.yaml`, its check and the required check `versions` are removed; CI's duration before and after is recorded
  - [x] CI is split into jobs that run in parallel in that image (style, static analysis, tests, browser tests, front end, audit), the browser tests after the front-end build; one final job `ci` needs them all and stays the only required check
  - [x] The `app` image carries PostgreSQL's and Redis's versions as labels, and the deploy writes them on the host for the production `compose.yaml`; a new PostgreSQL major is moved by dump and restore, tried once from 17 to 18 on a local machine
  - [x] Publishing a release builds the `app` and `web` images and pushes them to the GitHub Container Registry of the repository's owner (built locally for `linux/amd64`; the workflow first runs with E1.3e)
  - [x] A bootstrap playbook prepares a host from templates in the repository: Docker, nginx and certbot from the distribution's archive when missing, one nginx site with its certificate, a deploy user restricted to the deploy command, the production `compose.yaml` and secrets generated on the host; an installed Docker, nginx and their other sites are left untouched; a second run changes nothing
  - [x] The release workflow deploys over SSH, from a GitHub environment that waits for the maintainer's approval: pull, migrations, restart, static GTFS imported when missing or changed, health check, automatic return to the previous version when it fails (proven by hand through the deploy key; the workflow first runs with E1.3e)
  - [x] Going back to the previous release is one command, tried once
  - [x] `docs/deploy.md` takes a fork from nothing to a deployed release; no host name, address, port or credential is in the repository
  - [x] Proven on three local machines: Debian 13 and Ubuntu 26.04 freshly installed, and a Debian 13 already running Docker and nginx with another site
- E1.3e Live on the maintainer's server
  - [x] Bootstrap run, secrets and variables set, a release deployed by the workflow; the public URL answers 200 over HTTPS
  - [x] Memory used by the stack measured on the server
  - [x] One TBM poll timed on the server (E2.3)
- E1.3f Version upkeep, once the images start from the official ones (E1.3d)
  - [x] A weekly workflow opens or updates one pull request per newer supported version, with the token of the release App: PHP, PostgreSQL and Redis when their official image has a newer version, Node when a newer LTS line starts; each alone, rewriting the one place that names it (ADR-0020)
  - [x] The weekly workflow never fails on "nothing to raise", and its rules are tested on recorded answers of the version sources
- E1.5 Public accessibility statement and ecodesign statement, linked from every page (ADR-0006)
  - [x] A self-audit of the 106 RGAA 4.1.2 criteria on a sample of pages (the map page with and without a selected line, both statements), kept as a data file: each criterion conformant, not conformant or not applicable on each page, with what was checked and how; a test fails when a criterion or a page is missing
  - [x] `/accessibilite`: the accessibility statement on the model of the RGAA, in French: conformance status and rate computed from the audit (said to be a self-audit, not an independent one), non-conformant criteria and the content not accessible, with the alternatives offered (text view, pause), the technologies, tools and test environments, the date, the contact and the means of redress
  - [x] A self-assessment of the 78 RGESN 2024 criteria, kept as a data file: each criterion satisfied, not satisfied or not applicable, with its justification; a test fails when a criterion is missing
  - [x] `/ecoconception`: the ecodesign statement in French, from that file: score, criteria by status, the commitments of ADR-0006 and their measures (map page weight and JavaScript budget, bodyless `304`s, only changed positions), and what is planned for the criteria not satisfied
  - [x] Both linked from every page, rendered by the server without JavaScript, and checked by axe; contact through the repository's GitHub issues, no address in the repository

- E1.6 Scheduled commands' logs reach the container's logs ([story 025](stories/025-scheduled-logs.md)); found by E2.5: their log lines, failures included, went nowhere
  - [x] The poll and the import, run by the scheduler, write to the scheduler's own `stderr`, so their lines show in the container's logs, locally and on the server
  - [x] A test checks where every scheduled command's output goes; a poll's line is seen in the local scheduler's logs
- E1.7 Hardening from the code review of 2026-10-24 ([story 027](stories/027-hardening.md))
  - [x] A feed that cannot be read still ages vehicles out (E2.3), and a position at (0, 0), sent when the GPS is lost, is dropped
  - [x] The morning import runs in the background, so polls do not wait for it
  - [x] The endpoints answer 404 for a dotted network name; Escape closes the popup only from the popup or the map; the locale defaults to French
  - [x] Architecture tests: positions are written only by `RecordPositions`, and the map module imports neither Livewire nor Alpine; each shown to fail on a deliberate violation
  - [x] AGENTS.md describes the polling store, not a Reverb subscription that does not exist
- E1.8 The server's stores and guards, from the code review of 2026-10-24 ([story 028](stories/028-server-stores.md), [ADR-0021](adr/0021-redis-and-counted-versions.md))
  - [x] Cache, queue and sessions in Redis, locally and on the server; the tests keep in-memory drivers
  - [x] Position versions counted by PostgreSQL with each write, and the change log answers the whole state when a version is missing after the browser's, as a failed cache write or a Redis restart leaves it
  - [x] In production the application answers only for the exact host of `APP_URL`, and for none when it names none (`trustHosts`); the host's nginx sends HSTS on HTTPS
  - [x] A second rollback in a row is refused, and `docs/deploy.md` says how to go further back
  - [x] Checked by a dry run of the bootstrap on a local machine; the maintainer's run on the server and the checks after the next deploy are follow-ups in *Current state*
### E2: Ingestion
- E2.1 Import the static GTFS (routes, stops, colours)
  - [x] `php artisan network:import {network}` imports lines (namespaced id, short name, long name, mode, colour, text colour) and stops (namespaced id, name, coordinates) through the `gtfs` driver of `NetworkCatalog`; colours are optional (73 of 202 TBM lines have none)
  - [x] Only the files needed are read from the archive, streamed: memory stays flat whatever the size of `stop_times.txt` (21 MB archive for TBM)
  - [x] The import runs in one transaction and is idempotent: running it twice leaves the same data, a line or stop removed from the feed is removed
  - [x] The download sends `If-None-Match` with the last ETag and skips the import on `304` (RGESN)
  - [x] The scheduler runs the import daily, after the provider's nightly update
  - [x] Tests run against a small GTFS archive in `tests/fixtures`
- E2.2 Poll the GTFS-RT VehiclePositions feed on a schedule
  - [x] The protobuf / GTFS-RT library is chosen in an ADR before the driver is written
  - [x] The scheduler polls each network at its configured interval, 20 s for TBM (the feed changes every 20 s, measured 2026-10-16), without overlapping runs
  - [x] Each request sends `If-None-Match`; a `304` ends the run without parsing (RGESN)
  - [x] The `gtfs-rt` driver maps the feed to ADR-0013 positions, with each fallback covered by a test on a recorded payload
  - [x] A failed or invalid fetch is logged and nothing of its payload is written; the next run retries. Positions past the age limit still leave the current state (E2.3, E1.7)
  - [x] The architecture test fails on a deliberate GTFS-RT reference outside `App\Ingestion`, shown once and reverted (story 001 known limit)
- E2.3 Filter stale positions and keep the latest state per vehicle (ADR-0013)
  - [x] Positions more than 5 min older than their reception, or more than 2 min ahead of it, are dropped; both limits are per-network settings, and the number dropped is logged per run
  - [x] The single write path upserts the latest position per vehicle and emits `PositionsUpdated` with the changed positions only; an unchanged poll emits nothing
  - [x] A vehicle whose last position is older than the limit leaves the current state
  - [x] One TBM poll (about 400 vehicles) is processed in under one second on the production server, measured (locally: 0.35 s for a whole scheduled run; on the server, story 018: about 0.8 s of decoding and recording for 430 changed positions, 1.34 s for the whole command with Laravel's boot and the download)
- E2.4 A lighter poll ([story 021](stories/021-lighter-poll.md)); E2.3 measured about 1.3 s a poll on the server, more than half of it booting Laravel and recording
  - [x] The production image runs the CLI with OPcache and a file cache outside the code: each scheduled poll is a new PHP process
  - [x] `RecordPositions` reads the stored state as plain rows of the columns it compares, and builds the event's positions from the rows it writes instead of reading them back; its behaviour is unchanged
  - [x] A test shows that a poll reads the stored state once and writes once, whatever the number of vehicles
  - [x] Both are timed locally before and after on a recorded TBM feed, recorded in the story file; the server is timed after the next deploy
- E2.5 Read the feed as soon as it is published ([story 024](stories/024-poll-on-publication.md)); the scheduler's runs (:00, :20, :40) read TBM's feed 18 s after it was published (:02, :22, :42)
  - [x] Each poll waits until just after the feed's next publication, learnt from the time the last feed carries, so a provider that moves it is followed; the number of polls does not change
  - [x] An unchanged feed is asked again once, a little later; a failure of that second request counts as unchanged
  - [x] How long after its publication the feed was read is logged, and measured locally before and after, recorded in the story file; on the server after the next deploy

### E3: Live map
- E3.1 MapLibre map inside Livewire, open basemap without API key; Tailwind installed (ADR-0008)
  - [x] The home page shows a MapLibre map with OpenFreeMap's Positron style, fitted to the network's stops; no API key, no account
  - [x] The map is a TypeScript module in `resources/js/map/` that never imports Livewire or Alpine (ADR-0010); `tsc --noEmit` runs in CI
  - [x] Tailwind CSS v4 through its Vite plugin, with explicit `focus-visible` styles; the interface is in French (`lang="fr"`)
  - [x] The map bundle loads on the map page only; the JavaScript of the map page stays under 600 KB compressed, measured in CI and published in the README with the page's EcoIndex, measured locally (ADR-0006, ADR-0011)
  - [x] A Pest browser test loads the page and finds no axe violation; the map has an accessible name, labelled keyboard-reachable controls and a skip link to the text view
- E3.2 Live vehicles on the map, coloured by line and shaped by mode (ADR-0006, ADR-0013)
  - [x] The page reads positions from a JSON endpoint that returns only what changed since the browser's last request (moved and removed vehicles), with `ETag` and `304`; it asks every 5 s and stops while the tab is hidden (ADR-0006)
  - [x] Each vehicle is a small marker in its line's colour, shaped by mode (bus circle, tram square, ferry diamond, other or unknown triangle) so that colour is not the only cue, with an outline reaching 3:1 against the basemap; the line's short name is written beside the point, reaching 4.5:1 against the basemap
  - [x] Lines without a colour use a neutral colour; vehicles without a line are shown, labelled "?" on the map
- E3.3 Vehicle popup: line, vehicle id, age of last position
  - [x] Clicking a vehicle opens a popup with the line (short and long name, or "Ligne inconnue"), the vehicle id without the network prefix and the age of its last position ("il y a 25 s"), kept current
  - [x] Keyboard users open the same popup from the text view (delivered with E3.5; canvas vehicles cannot take focus, ADR-0011; RGAA test 7.3.1 accepts an element of the same page doing the same action)
  - [x] Escape, or the close button, closes the popup and returns focus to where it was; the popup follows its vehicle and closes if the vehicle disappears
- E3.4 Select a line or a vehicle and let the map follow it
  - [x] A labelled line selector highlights that line without hiding the others: its vehicles drawn larger and over the rest, its group first in the text view, the map fitted to them; choosing a vehicle (popup or text view) selects its line and makes the map follow it on each update
  - [x] Moving the map by hand stops following; closing the popup, or a × link next to the selector, deselects the line; the selection is kept in the URL (`?ligne=`, `?vehicule=`) so it can be shared and survives a reload
  - [x] The selected line's shape, from the network file of E3.8, is drawn over the others at the same width, still under its vehicles, in a colour that reaches 3:1 against the basemap (WCAG 1.4.11), measured and recorded in the story file
  - [x] The selector lists only the lines that have a vehicle now, kept up to date while the page stays open but never while the selector has the focus (RGAA); the selected line stays listed without vehicles, so that a shared link keeps working; every line's shape stays on the map
  - [x] A mode selector before the line selector narrows its list to one mode (tram, bus…), among the modes that run now, without changing the selected line; without JavaScript the form filters by reloading
- E3.5 Accessible text view equivalent to the map: lines, their vehicles and the age of the last position (ADR-0006)
  - [x] The same data as the map, rendered on the server: lines sorted by name, each with its vehicles and the age of their last position, including "Ligne inconnue"; readable before JavaScript loads
  - [x] Each vehicle has a button that opens its popup on the map (E3.3, RGAA 7.3.1)
  - [x] Live updates never move the focus and do not announce every change; a single polite status line gives the time of the last update
  - [x] Checked with the keyboard only and with VoiceOver; no axe violation; findings recorded in the story file
  - [x] Line data (short and long name, mode, colour) comes from a line catalogue endpoint, fetched in parallel with the first positions and cached with `ETag`; positions carry only the line id, and the vehicle number shown is derived from the vehicle id in the browser (RGESN)
  - [x] A position naming a line the catalogue lacks triggers one conditional refetch, repeated at most once an hour while that line stays unknown (the daily import may add it); a line still unknown, or a catalogue that failed to load, shows the vehicle like one without a line
  - [x] On a landscape screen the text view sits beside the map; on a portrait screen it is a side panel over the map, opened and closed by a button (`aria-expanded`), and shown under the map without JavaScript. Choosing a vehicle in the panel closes it to show the popup; Escape on the popup reopens the panel on that vehicle
- E3.6 Pause and resume live updates (ADR-0006)
  - [x] A button with `aria-pressed` stops and resumes updates of the map and the text view together (RGAA 13.8); no request is sent while paused
  - [x] Resuming catches up with one request, then the normal interval resumes
- E3.7 Group vehicles by line and destination ([story 013](stories/013-line-and-destination.md))
  - [x] A vehicle's destination is its `vehicle.label`, as is, on a network whose configuration says the label is a destination (TBM, ADR-0018); elsewhere it has none
  - [x] The text view nests two levels of headings: each line, then its destinations sorted by name with "Destination inconnue" last, then the vehicles; the popup shows the destination
  - [x] A vehicle is named by its mode, line and destination ("Tram A - La Gardette", ADR-0018) in the text view and the popup, its number without a destination; the text view's buttons keep distinct accessible names with the number
  - [x] Positions carry the destination (ADR-0018); the payload growth is measured again and recorded in the story file (RGESN)
  - [x] Coverage checked over a day (morning, midday, evening): positions with a label, label stable along a trip, positions without a line and how many trip updates fill; recorded in the story file
- E3.8 Every line of the network on the map, faint, under the vehicles (ADR-0016); comes before E3.4
  - [x] `network:import` writes one GeoJSON document per network, one feature per line (its shapes merged, duplicates removed), only when the shapes or their lines changed, not on every new archive; kept in the database since story 026, where every container reads the same one
  - [x] The simplification tolerance is chosen by testing at street and city zoom levels; tolerance and compressed weight are recorded in the story file
  - [x] A lines-shapes endpoint serves the file with `no-cache` and its fingerprint as `ETag` (`304` on a revisit); the map fetches it after the first positions
  - [x] Every line is drawn in its colour, thin and mostly transparent, under the vehicles; the text view is unchanged, the shapes being decorative
- E3.9 A "Signaler un problème" link ([story 022](stories/022-report-a-problem.md))
  - [x] The map page links to the repository's issue chooser in the same tab (RGAA 13.2), "Signaler un problème", with a name that says it opens GitHub
  - [x] Three issue forms, in French like the site: a bug, a feature request, and wrong data (what is wrong, the line, the vehicle number, the time, the details); blank issues are disabled
  - [x] The repository's URL comes from the configuration, with this repository as the default

### E4: Real-time push
- E4.1 Broadcast position updates with Reverb
- E4.2 Fallback to periodic refresh when the socket drops

### E5: Replay and load
- E5.1 Record raw feed payloads
- E5.2 Replay feed, selectable in configuration
- E5.3 Load test (k6) against cached endpoints, results published in the README

### E6: Second network
- E6.1 Add a second French network through configuration only

### E7: Network on the map and in text
Not in a milestone yet; criteria to be written ([ideas/network-on-map.md](ideas/network-on-map.md)).
- E7.1 Stations on the map from a street zoom level, cached like the line catalogue
- E7.2 One server-rendered page per line (`/lignes/{line}`) listing its stops in order for each direction, from one representative trip per direction

## Later

- A link in the vehicle popup that opens the wrong-data issue form filled with the line, the vehicle number and the time (E3.9)
- Position history, stage 2: short window (24–48 h), e.g. a vehicle's recent trail (ADR-0002)
- Position history, stage 3: long retention for statistics such as delays per line (ADR-0002)
- First spatial feature (stops and vehicles around me, viewport filtering), bringing PostGIS (ADR-0002)
- SIRI-VM and NeTEx drivers, when a network needs them (ADR-0001)
- Next departures at a stop and next stops of a vehicle ([ideas/next-departures.md](ideas/next-departures.md)): GTFS-RT TripUpdates, perhaps without static stop times (TBM's cover 9 h ahead, measured 2026-10-20), behind the anticipated trip updates port (ADR-0001); needs the brief's scope changed (no timetables), storage of stop times to be discussed, after E7.2
- Vector tiles for line shapes (PostGIS or PMTiles), when a second network makes the GeoJSON files too heavy (ADR-0016)
- Brand web font, chosen with a visual identity; system fonts until then (ADR-0008)
- `laravel/pail` to filter live logs, once ingestion produces logs worth filtering (ADR-0007)
- Bearing arrows and destination labels on vehicles (ADR-0013)
- Line of a position without a `route_id` from its trip update, with the trip updates port: TBM `nt_` trips are the only cause, 283 positions of 908,605 (0.03 %) over 2026-10-20 and 21, all filled by trip updates ([story 013](stories/013-line-and-destination.md#study))
- Cloudflare in front (DNS, proxy, TLS, cache rules), formerly E1.4: its free plan needs the whole DNS zone, which the maintainer keeps where it is (2026-10-24); the host's nginx serves the site, its assets cached a year by browsers
- `SECURITY.md` with private vulnerability reporting, pull request template mirroring the definition of done
