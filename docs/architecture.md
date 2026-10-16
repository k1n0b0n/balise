# Architecture

> Status: draft. Decisions are recorded in [adr/](adr/); this page summarises them.

## Principles

- **Proportionate rigour.** Ports and adapters only where a real boundary exists (external feeds). Laravel conventions everywhere else.
- **Rules are enforced, not just written.** Architecture tests fail the build when a boundary is crossed.

## Components

| Component | Role | Decided in |
|---|---|---|
| `VehicleFeed` port | Live positions of a network, whatever the standard | [ADR-0001](adr/0001-feed-ingestion-ports.md) |
| `NetworkCatalog` port | Lines, stops and shapes of a network | ADR-0001 |
| Drivers (MVP) | `gtfs-rt`, `gtfs`, `replay`; `siri-vm`, `netex` only when a network needs them | ADR-0001 |
| Network configuration | One entry per network: drivers, URLs, timezone, id prefix | ADR-0001 |
| Scheduler + queue | Poll feeds, process positions; every write emits `PositionsUpdated` | ADR-0002 |
| Livewire + MapLibre | Map UI; the map and the live store are a framework-agnostic TypeScript module, mounted by Livewire | [ADR-0010](adr/0010-ui-stack.md) |
| Reverb | Push position updates to browsers | — |

## Stack

| Layer | Choice | Status |
|---|---|---|
| Framework | Laravel (latest stable), Livewire, Alpine; Inertia + Vue as the planned exit | Decided, [ADR-0010](adr/0010-ui-stack.md) |
| Map | MapLibre GL JS, loaded on the map page only | Decided, [ADR-0011](adr/0011-map-library.md) |
| CSS | Tailwind CSS v4, installed with E3.1; system fonts | Decided, [ADR-0008](adr/0008-css-approach.md) |
| Basemap tiles | OpenFreeMap (no key); self-hosted Protomaps PMTiles as fallback | Decided, ADR-0011 |
| Database | PostgreSQL; PostGIS later, with the first spatial story | Decided, [ADR-0002](adr/0002-database.md) |
| Cache / queue | Redis | To confirm |
| Real-time | Laravel Reverb | Agreed |
| Hosting | Oracle Cloud Always Free A1 (arm64, Ubuntu 26.04), provisioned with OpenTofu and Ansible, one server | Decided, [ADR-0012](adr/0012-hosting.md) |
| Edge | Cloudflare (free plan): DNS, proxy, TLS, cache rules | Decided, ADR-0012 |
| Quality gates | Pint (`laravel` preset), Larastan (level max), Pest with `arch`, behind `composer qa` | Decided, [ADR-0005](adr/0005-quality-tooling.md) |
| Local environment | `compose.yaml`, PHP and nginx on Ubuntu 26.04 like the server, versioned app config | Decided, [ADR-0007](adr/0007-local-development-environment.md) |
| Responsible design | RGAA 4.1.2 AA, RGESN 2024 self-assessment | Decided, [ADR-0006](adr/0006-responsible-design.md) |
| Delivery | Protected `main`, squash merge, release-please, Ansible deploy on release | Decided, [ADR-0004](adr/0004-delivery-workflow.md) |

## Open questions

1. Feed polling interval versus the provider's tolerance.
2. Normalised model: exact fields of a position (ADR-0001 consequence).
