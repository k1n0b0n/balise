# Story 034: The trip updates port, decoded by the C extension

- **PRD:** E8.1
- **Status:** Done

## Acceptance criteria

See E8.1 in [prd.md](../prd.md); the decoding is [ADR-0023](../adr/0023-protobuf-c-extension.md), the study [ideas/next-departures.md](../ideas/next-departures.md).

## Tasks

- [x] `docker/php/install-extensions.sh` builds `protobuf` 5.36.2 from PECL, checked by SHA-256, in every target; `ProtobufExtensionTest` compares its version with `google/protobuf` in `composer.lock`
- [x] The port `TripUpdateFeed` and its types (`TripUpdate`, `StopTime`, `TripStatus`); the driver `GtfsRealtimeTripUpdateFeed`, which refuses to read without the extension; `TripUpdateFeeds` builds it from `networks.{network}.trip_updates`, or nothing without a URL or while replaying
- [x] `Departures::at($station, $now)`: the feed read on the first request, then kept a minute (`keep_seconds`) under a lock, one cache key per platform under the read's version; a failed read logged and kept a minute as no data
- [x] Tests: the decoding (ids, statuses, departure then arrival time, skipped, no time), a station's departures from its platforms in time order, one read a minute, no data without trip updates or while replaying, the failed read

## Notes

- **Measured on the live TBM feed** (2026-10-26, 15:14): 2.3 MB, 1,759 trips, 61,217 stop times; download 0.36 s, decoding 0.15 s, a first request 0.6 s, the next ones 1 ms; one read adds 67 MB to the peak memory of a request, under php-fpm's 128 MB; 3,230 platforms kept, 8.3 MB in Redis for a minute. Every stop id of the feed is in the static stops.
- **Cancelled trips and skipped stops carry no time** on TBM: none of the 33 cancelled trips has a stop time, none of the 531 skipped stops a time. From trip updates alone, they cannot be placed at a station: the port keeps them, `Departures` lists only what has a time. E8.2's "cancelled departures shown" needs either the static stop times (out of scope, PRD 2026-10-26) or to be dropped.
- **Quinconces is 25 stations in TBM's data**, all named "Quinconces": the tram pole (`TQUIN_B`, 5 platforms, 404 departures this afternoon) and lone bus platforms around it. The search (E7.1) opens one of them; a station page (E8.2) will have to group them, by name and distance.
- **Not polled**: nothing reads trip updates while nobody asks (RGESN); the lock makes concurrent first requests wait for one download.
