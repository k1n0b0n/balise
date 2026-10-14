# ADR-0001: Feed ingestion behind standard-agnostic ports

- **Status:** Proposed
- **Date:** 2026-10-14

## Context

- The long-term vision is several networks and cities.
- Networks do not all speak the same standard:
  - **GTFS-Realtime** (protobuf) for live positions, with **GTFS** for static data. TBM publishes both.
  - **SIRI** (SIRI-VM, SIRI Lite) is the European real-time standard; TBM also publishes SIRI Lite.
  - **NeTEx** is the European static standard; some networks publish NeTEx only.
  - Some networks only expose a proprietary JSON API.
- The live feed is empty at night and unsuitable for repeatable tests or load tests.
- The rest of the application (map, broadcasting, storage) must not depend on any of these formats.

## Decision

1. **Two ports, owned by the application, shaped by our needs rather than by a standard:**
   - `VehicleFeed`: returns the current vehicle positions of a network.
   - `NetworkCatalog`: returns the network's lines, stops and shapes.
2. **One adapter per standard, not per network.** Each network declares in configuration which driver it uses and with which settings (URLs, timezone, id prefix).
   - MVP drivers: `gtfs-rt` (VehicleFeed), `gtfs` (NetworkCatalog), `replay` (VehicleFeed, recorded snapshots).
   - Anticipated drivers, **not built until a network needs them**: `siri-vm`, `netex`, custom JSON.
3. **A normalised internal model** (network, line, stop, vehicle, position). It is inspired by GTFS but owned by the application, and ids are namespaced per network. GTFS tables are never mirrored as-is.
4. **Plain Laravel outside the ingestion boundary.**
5. **The boundary is enforced by architecture tests:** no class outside the ingestion namespace may reference a GTFS, protobuf or SIRI type.

## Alternatives considered

| Option | Why not |
|---|---|
| Full hexagonal / DDD across the app | Ceremony out of proportion with a map of vehicles; hurts readability for a ten-minute review |
| Plain Laravel, feed parsing inside a job | Couples the app to one network and one format, makes replay and tests harder |
| One adapter per network (`TbmFeed`, …) | Duplicates what the standards already unify |
| Store GTFS tables as-is | Fast to start, but every non-GTFS standard would then have to be converted into GTFS |
| Build SIRI and NeTEx drivers now | No network in scope needs them; speculative code to maintain |

## Consequences

- A network on an already-supported standard is added through configuration only.
- A network on a new standard costs one adapter, with no change to the domain, the map or broadcasting.
- Replay mode, deterministic tests and load tests use the same code path as production.
- Network-specific quirks go into per-network configuration first, and into a decorator around the adapter only if configuration is not enough.
- The normalised model has to be designed carefully up front: a field that GTFS has and SIRI lacks must be optional.
