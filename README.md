# balise

Live map of public transport vehicles, fed by open GTFS-Realtime data. First network: TBM (Bordeaux).

> Status: skeleton (E1.1). No map yet.

## Run it

```sh
docker compose up -d
```

Then open http://localhost:8000. Quality gates: `docker compose exec php composer qa`. More commands in [AGENTS.md](AGENTS.md#commands).

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

Bordeaux Métropole, *Réseau urbain et scolaire TBM*, published on [transport.data.gouv.fr](https://transport.data.gouv.fr/datasets/offres-de-services-bus-et-tramway-gtfs) under the Licence Ouverte.
