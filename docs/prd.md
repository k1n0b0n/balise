# Product requirements

> Status: draft. Acceptance criteria to be written together before any story starts.

## Milestones

| Milestone | Epics | Meaning |
|---|---|---|
| **M1: Visible** | E1, E2, E3 (without real-time push) | Public URL shows live vehicles, CI green, automated deploy |
| **M2: Real-time** | E4, E5 | Positions pushed over WebSockets, replay mode, measured load |
| **M3: Multi-network** | E6 | Second network added by configuration |

## Epics and stories

### E1: Foundation and delivery
- E1.1 Laravel + Livewire skeleton with quality gates (Pest, PHPStan, Pint, architecture tests)
- E1.2 CI on GitHub Actions: `composer qa`, dependency audit, Conventional Commits check on pull request titles, Dependabot (ADR-0004, ADR-0005)
- E1.3 Server provisioned with Forge, deploy on GitHub release created by release-please (ADR-0004)
- E1.4 Cloudflare in front (DNS, proxy, cache rules)

### E2: Ingestion
- E2.1 Import the static GTFS (routes, stops, colours)
- E2.2 Poll the GTFS-RT VehiclePositions feed on a schedule
- E2.3 Filter stale positions and keep the latest state per vehicle

### E3: Live map
- E3.1 MapLibre map inside Livewire, open basemap without API key
- E3.2 Vehicles as points coloured by line, with clustering
- E3.3 Vehicle popup: line, vehicle id, age of last position
- E3.4 Select a line or a vehicle and let the map follow it

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
- AI pull request review bot (CodeRabbit, Greptile): evaluate after a few stories, non-blocking
- `SECURITY.md` with private vulnerability reporting, pull request template mirroring the definition of done
