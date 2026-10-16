# Story 003: Import the static GTFS

- **PRD:** E2.1
- **Status:** Done (merged in #22; deployment comes with E1.3)

## Acceptance criteria

See E2.1 in [prd.md](../prd.md).

## Tasks

- [x] `config/networks.php`: one entry per network, keyed by its id prefix, with its catalogue driver (ADR-0001)
- [x] Port `NetworkCatalog` and `gtfs` driver in `App\Ingestion`: download to disk, `If-None-Match`, rows streamed from the archive
- [x] Tables `lines`, `stops`, `catalog_imports`; action `ImportNetworkCatalog`, command `network:import`, daily schedule at 05:30 network time
- [x] Feature tests on a fixture archive built from readable files in `tests/fixtures/gtfs/minimal`

## Notes

- No new dependency: `ZipArchive` streams one entry at a time and `fgetcsv` reads it row by row.
- Real TBM import (2026-10-16): 202 lines (193 bus, 6 tram, 3 ferry), 5,233 stops, 1.6 s; peak memory 48.5 MB before, 52.5 MB after, for a 21 MB archive. The next run gets `304` and takes 0.1 s.
- Only stops and platforms (`location_type` empty or 0) are imported: 2,189 TBM rows are stations or other nodes that no vehicle serves.
- Removed lines and stops are found with a `sync_id` written by each run, not a timestamp: two runs in the same second stay correct.
- A line without `route_short_name` takes its long name (TBM's BatCub ferry).
- 73 of 202 TBM lines have no colour, and 56 of the 129 colour pairs are below 4.5:1: colours are stored as published, contrast is handled when displaying them (E3.2).
- New folders: `app/Actions` (one class per business operation) and `app/Enums`.
- CodeRabbit's first review (#22) found three real issues, all fixed with a test: an empty `routes.txt` would have wiped the catalogue (now an error, and a catalogue without lines is refused), a row longer than the header aborted the import, and a failed download left its temporary file behind.
