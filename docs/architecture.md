# Architecture

> Status: draft. Decisions are recorded in [adr/](adr/); this page summarises them.

## Principles

- **Proportionate rigour.** Ports and adapters only where a real boundary exists (external feeds). Laravel conventions everywhere else.
- **Rules are enforced, not just written.** Architecture tests fail the build when a boundary is crossed.

## Components

| Component | Role | Decided in |
|---|---|---|
| `VehicleFeed` port | Live positions of a network, whatever the standard | [ADR-0001](adr/0001-feed-ingestion-ports.md) (proposed) |
| `NetworkCatalog` port | Lines, stops and shapes of a network | ADR-0001 |
| Drivers (MVP) | `gtfs-rt`, `gtfs`, `replay`; `siri-vm`, `netex` only when a network needs them | ADR-0001 |
| Network configuration | One entry per network: drivers, URLs, timezone, id prefix | ADR-0001 |
| Scheduler + queue | Poll feeds, process positions; every write emits `PositionsUpdated` | ADR-0002 |
| Livewire + MapLibre | Map UI; the map is a JS island isolated from Livewire re-renders | — |
| Reverb | Push position updates to browsers | — |

## Stack

| Layer | Choice | Status |
|---|---|---|
| Framework | Laravel (latest stable), Livewire, Alpine | Agreed |
| Map | MapLibre GL JS | Agreed |
| Basemap tiles | OpenFreeMap (no key) | To confirm |
| Database | PostgreSQL; PostGIS later, with the first spatial story | Decided, [ADR-0002](adr/0002-database.md) |
| Cache / queue | Redis | To confirm |
| Real-time | Laravel Reverb | Agreed |
| Hosting | OVH VPS-1 (Ubuntu 24.04) provisioned by Laravel Forge | Agreed, ADR to write |
| Edge | Cloudflare (free plan) | Agreed |

## Open questions

1. Feed polling interval versus the provider's tolerance.
2. Normalised model: exact fields of a position (ADR-0001 consequence).
