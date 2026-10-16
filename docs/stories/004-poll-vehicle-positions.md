# Story 004: Poll the GTFS-RT VehiclePositions feed

- **PRD:** E2.2
- **Status:** Review

## Acceptance criteria

See E2.2 in [prd.md](../prd.md).

## Tasks

- [x] ADR-0015: `google/protobuf` runtime, classes generated from the official proto into `generated/` (ignored by Git, built by setup and CI)
- [x] `scripts/generate-gtfs-realtime.sh`: committed proto (pinned commit, `--update-proto` to refresh), `protoc` 36.2 with checked SHA-256, proto2 converted to proto3
- [x] Port `VehicleFeed`, value object `VehiclePosition` (ADR-0013) and `gtfs-rt` driver with the fallbacks
- [x] Command `network:poll`, scheduled at each network's interval (20 s for TBM) without overlapping; ETag kept in the cache
- [x] Tests on a recorded TBM payload (`tests/fixtures/gtfs-rt`) and on built payloads for each fallback
- [x] Boundary rule proven: a class in `App\Models` using `Transit_realtime\FeedMessage` failed the architecture test ("Expecting 'App' not to use 'Transit_realtime'"), then was removed

## Notes

- `proto/` holds the pristine upstream schema. The 41 generated files were first committed, then made a build artifact during review: the script already gave a pinned, checksummed and reproducible build.
- The PHP decoding of a recorded TBM payload matches Google's Python bindings field by field (403 vehicles: id, coordinates, timestamp, line, trip); decoding takes 17 ms.
- Live run at 16:05 on 2026-10-16: 538 vehicles, 1 without a line, 158 ms for download and decoding; the next run within 20 s got `304`. The scheduler runs the poll at :00, :20 and :40 of each minute, in about 0.4 s.
- Positions are read but not stored yet: the write path, the freshness filter and `PositionsUpdated` are E2.3.
- The pure-PHP runtime is fast enough; the C extension is not needed (ADR-0015).
- `composer require` ran `boost:update`, which added Boost's "tests rules" to `AGENTS.md`.
- CodeRabbit (#26) found four issues, all fixed with tests: an empty or headerless payload is now an error instead of "0 positions", impossible coordinates are dropped, the poll lock expires after one minute instead of 24 hours after a crash, and generation reruns when the script or the proto changes or a run was interrupted (stamp written last, output moved into place atomically).
