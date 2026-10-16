# Story 005: Filter stale positions and keep the latest state per vehicle

- **PRD:** E2.3
- **Status:** Review

## Acceptance criteria

See E2.3 in [prd.md](../prd.md).

## Tasks

- [x] Table `vehicle_positions` (one row per vehicle, ADR-0013) and model `App\Models\VehiclePosition`
- [x] Single write path `App\Actions\RecordPositions`: freshness filter, latest position per vehicle, upsert of changed rows only, removal of vehicles that aged out, `PositionsUpdated` after the transaction
- [x] Event `App\Events\PositionsUpdated`: network, changed positions, removed vehicle ids
- [x] `network:poll` records every snapshot; a `304` still ages vehicles out
- [x] Freshness limits per network: `max_age_seconds` (300) and `max_ahead_seconds` (120) in `config/networks.php`

## Notes

- A feed can list the same vehicle twice; the latest position wins, otherwise PostgreSQL would refuse the upsert.
- Live TBM at 19:56 on 2026-10-16: 390 vehicles read, 24 dropped as stale, 366 changed, 2 removed; decoding 130 ms, recording 23 to 46 ms, about 0.35 s for the whole scheduled run. Almost every vehicle moves between two polls, so the "changed only" rule mostly filters vehicles at a standstill.
- The last criterion (under one second on the production server) stays open until the server exists (E1.3).
- Payload helpers for GTFS-RT tests moved to `tests/Pest.php`, shared by the poll and record tests.
- CodeRabbit (#27) found one issue, fixed with a test: a late report, older than the stored position but still fresh, used to overwrite it and go out as a move; the newer stored position now wins.
