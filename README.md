# balise

Live map of public transport vehicles, fed by open GTFS-Realtime data. First network: TBM (Bordeaux).

> Status: ingestion done (E2): static GTFS import, live polling every 20 s, latest position per vehicle in PostgreSQL. Map page with the basemap (E3.1); vehicles on the map come with E3.2.

## Run it

```sh
docker compose up -d
```

Then open http://localhost:8000 for the map. Import TBM's lines and stops: `docker compose exec php php artisan network:import tbm`. Quality gates, browser tests included: `docker compose run --rm test`. More commands in [AGENTS.md](AGENTS.md#commands).

## Weight of the map page

| Measure | Value | Budget |
|---|---|---|
| JavaScript, compressed (MapLibre, its worker, Livewire), checked in CI | 495.8 KB | 600 KB |
| [EcoIndex](https://www.ecoindex.fr/) (44 DOM nodes, 21 requests, 1,219 KB of which 713 KB of basemap tiles) | **A**, 86/100 | — |

Measured on 2026-10-16 (story 006); the EcoIndex is measured locally and will change as vehicles and the text view arrive.

## Documentation

| Document | Purpose |
|---|---|
| [docs/README.md](docs/README.md) | How this project is run (BMAD-lite workflow) |
| [docs/brief.md](docs/brief.md) | Problem, audience, vision, MVP scope |
| [docs/prd.md](docs/prd.md) | Epics and stories with acceptance criteria |
| [docs/architecture.md](docs/architecture.md) | Stack, components, deployment |
| [docs/adr/](docs/adr/) | Architecture Decision Records |
| [docs/stories/](docs/stories/) | One file per story in progress or done |

## License

Code under the [MIT license](LICENSE) ([ADR-0003](docs/adr/0003-license.md)). Data keeps its own license, below.

## Data

Bordeaux Métropole, *Réseau urbain et scolaire TBM*, published on [transport.data.gouv.fr](https://transport.data.gouv.fr/datasets/offres-de-services-bus-tram-et-scolaire-au-format-gtfs-netex-gtfs-rt-siri-lite) under the Licence Ouverte.
