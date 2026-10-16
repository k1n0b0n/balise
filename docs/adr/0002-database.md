# ADR-0002: Database engine and spatial support

- **Status:** Accepted
- **Date:** 2026-10-14
- **Amended:** 2026-10-16: hosting moved off Forge to an Oracle Cloud server provisioned with OpenTofu and Ansible (ADR-0012): where this ADR says "Forge" or "Forge recipe", read "the Ansible playbook"

## Context

What the application stores:

| Data | Volume (TBM) | Access pattern |
|---|---|---|
| Static network (lines, stops, shapes, trips) | ~200 lines, ~5,100 stops; trips and stop times are the bulk | Bulk import once a day, many reads |
| Latest position per vehicle | ~500 rows | Overwritten every poll, read by every client |
| Position history | Grows with time, depends on the retention chosen | Append-only; read by replay and possible statistics |

- Today's map needs no spatial query: every vehicle of the network is displayed.
- Spatial queries appear with the vision: vehicles in the viewport once there are several cities, nearest stops, snapping a position onto a line's shape, vector tiles for stops and shapes.
- Forge provisions MySQL by default; PostgreSQL is an option at server creation. PostGIS is not provisioned by Forge: it is one `apt` package plus `CREATE EXTENSION postgis`, scriptable as a Forge recipe.
- The latest positions are hot data. They belong in Redis or in memory whatever the engine; the database is the system of record, not the broadcast path.

## Options

### A. PostgreSQL + PostGIS from day one

| For | Against |
|---|---|
| Real GIS toolset: `geography` type, GiST indexes, distance and containment functions, `ST_AsMVT` for vector tiles | Not provisioned by Forge: one more server step to script and document |
| Spatial features of the vision need no migration later | Unused in M1 if no spatial feature is in the MVP: a dependency carried without value |
| Visible proof of GIS skills | Eloquent has no native spatial casts: needs a package (e.g. Magellan) or raw expressions |
| Native partitioning for history, `jsonb` for raw feed payloads | Local dev needs a PostGIS image instead of the stock Postgres one |

### B. PostgreSQL now, PostGIS when a story needs it

| For | Against |
|---|---|
| The costly choice (the engine) is made now; PostGIS is cheap to add later (extension + migration) | Coordinates stored as plain floats until then, migrated to `geography` later |
| No unused dependency in M1 | The GIS demonstration only happens when such a story ships |
| Same Postgres advantages as A (partitioning, `jsonb`) | |

### C. MySQL 8

| For | Against |
|---|---|
| Forge default, zero extra provisioning | Spatial support is narrower: no vector tiles, fewer functions, weaker tooling |
| Widely used in Laravel shops | Moving to PostgreSQL later, if the vision needs it, is a costly migration |
| | No PostgreSQL-style partitioning or `jsonb` |

### D. SQLite

| For | Against |
|---|---|
| Laravel's default, no server process, trivial local dev | One writer at a time: the poller and the importer compete for writes |
| | Spatial support only through SpatiaLite, awkward to deploy |
| | Not what a production Laravel app on Forge usually runs, so it shows little |

## Decision

**Option B: PostgreSQL now, PostGIS with the first story that queries space.**

- The engine is the decision that is costly to reverse; PostgreSQL keeps every spatial door open.
- No spatial feature is in the MVP, so PostGIS would be an unused dependency in M1. Adding it later costs one extension, one Forge recipe step and one migration (floats to `geography`), recorded in its own ADR.
- Local development uses a PostgreSQL Alpine image; it switches to a PostGIS image when PostGIS arrives.

### Position history: progressive

| Stage | Retention | When | What it adds |
|---|---|---|---|
| 1 | None: latest position per vehicle only | M1 | Nothing |
| 2 | Short window (24–48 h) | When a story needs it (e.g. a vehicle's recent trail) | A history table indexed on time, a scheduled purge |
| 3 | Long | When statistics enter the PRD | Partitioning by day and a retention policy, in a dedicated ADR |

This only stays additive if **every position write goes through one place that emits a `PositionsUpdated` event**. History is then a listener on that event, added at stage 2 without touching ingestion.

**Replay does not depend on history.** It records the raw feed payloads and plays them back through the real driver, so replay also exercises the parsing code. Stage 1 is therefore compatible with M2.

## Alternatives rejected

- **A (PostGIS from day one):** no spatial story in the MVP; carrying it unused adds provisioning and dev setup for nothing yet.
- **C (MySQL):** narrower spatial support, and a later move to PostgreSQL would be costly.
- **D (SQLite):** single writer, awkward spatial support, unrepresentative of a Forge production setup.

## Consequences

- Coordinates are stored as plain `latitude` / `longitude` columns until PostGIS arrives, then migrated to `geography(Point, 4326)`.
- The server's Ansible playbook installs PostgreSQL (originally: the Forge server is created with PostgreSQL selected, not the MySQL default).
- The `PositionsUpdated` event is part of the ingestion contract from the first story.
