# Idea: next departures at a stop, next stops of a vehicle

- **Status:** Planned → E8 (2026-10-26): next departures only, from trip updates only, read on demand and kept a minute, decoded by the C extension; departures in the station's popup, each station on its own, the destination from `trip_headsign` (E8.2, decided 2026-10-26)
- **Related:** ADR-0001 (trip updates port), ADR-0015 (protobuf), ADR-0018 (destination), [network-on-map.md](network-on-map.md) step 5, E4, E7.1, E7.2

## Why

The map shows where vehicles are; a rider then asks when the next one comes to their stop, and where the one in front of them stops next. Both answers are in GTFS-RT trip updates, which ADR-0001 kept for a third port "until next departures enter the PRD". The brief puts timetables out of the MVP.

## Measures (live TBM, recorded payload of 2026-10-20 at 15:35)

| Measure | Value |
|---|---|
| Feed (`/gtfsfeed/realtime/bordeaux`) | 2.7 MB, not compressed by the producer (0.85 MB gzipped); a new version every minute (27 in 27 min) |
| Download | 0.3 s from the workstation, up to 2 s at midday |
| Decoding, pure-PHP runtime (current, ADR-0015) | 1.2 to 1.4 s, 344 MB peak memory |
| Decoding, C extension `protobuf` 5.36.2 (PECL, built in 18 s on Alpine) | 0.03 s, 11 MB peak memory: 40 times faster, 30 times less memory |
| Trips | 2,041: 1,983 scheduled, 57 cancelled, 1 added; up to 9 h ahead |
| Stop time updates | 69,568: all with an absolute time, 467 skipped stops, 777 without data; stops already passed are kept |
| Stops with a departure in the next hour | 3,125 of 5,233 platforms; every stop id of the updates is in the static stops |
| Running vehicles | 420 with an update; median 22 stops left, up to 88 |
| Static GTFS of the day | `stop_times.txt` 193 MB, 2,570,058 rows; `trips.txt` 67,341 trips, 66,922 with a `trip_headsign` |
| Rhythm (measured 2026-10-25 around midnight, a request every 10 s for 3 min) | A new version about every 20 s, like the positions (the prototype read it once a minute, hence "every minute" above); about 300 KB at night |
| Conditional requests | The producer ignores `If-None-Match` on this feed: every read is a full `200` |
| Times | `time` is absolute (seconds since the epoch, UTC) and already includes the delay; `delay` (seconds) is `time` minus the scheduled time |

## Decisions to weigh

Each decision lists its options with what speaks for and against them, then the strongest case against the option preferred.

### 1. The brief's scope

| Option | For | Against |
|---|---|---|
| **Keep timetables out** | The MVP stays a live map, small enough to be read in ten minutes; no new port, no new feed | The first question of a rider at a stop stays unanswered; the map alone is a demonstration more than a service |
| **Open it to next departures only** (no timetables, no trip planning) | Real-time only, the same nature as the map; reuses E3.7's grouping and the trip updates port ADR-0001 anticipated | A second feed of 2.7 MB a minute; a stop page and a way to reach stops (E7.1 or E7.2) come first |
| **Open it to full timetables** | Works at night and when the feed fails | Static stop times (2.6 million rows a day of feed), calendars, a different product |

Preferred: next departures only. **Against it:** E7.1 and E7.2 are not written yet; opening the scope now adds a story chain (stations, stop page, port, feed) before the MVP is deployed (E1.3, E1.4 still open).

### 2. The source of departures

| Option | For | Against |
|---|---|---|
| **Trip updates only** | One source for planned, added and cancelled trips; 9 h ahead covers the day; no import of stop times | Nothing when the feed is down or empty (night); a stop whose next departure is beyond the horizon shows none |
| **Static stop times corrected by trip updates** | Answers at night and during a feed failure; the standard approach of journey planners | 2.6 million rows rebuilt every night, calendars and exceptions to resolve, and two sources to reconcile (trip ids, `nt_` trips absent from the static) |
| **Static stop times only** | No live feed | Wrong as soon as a vehicle is late, which is what riders want to know |

Preferred: trip updates only. **Against it:** "no departure" at night may read as "no service" or as "broken"; the page has to say why ("Pas de données temps réel"), and riders may still expect the first morning departure, which only the static data gives.

### 3. When trip updates are read

| Option | For | Against |
|---|---|---|
| **On demand**, on the click, cached a minute | Nothing read without a request | The first click after a quiet minute waits for download and decoding: 1.5 to 3.5 s in pure PHP, 0.3 to 2 s with the C extension, where the download then dominates |
| **Every minute while someone looks** (a page open in the last few minutes) | Clicks read the cache, nothing read at night or without visitors | One visitor makes the server read 160 MB an hour; needs a signal of presence (the positions requests) and a scheduled task that stops itself |
| **Every minute, always** | Simplest; ready for history or statistics later | 3.9 GB a day downloaded and decoded for nobody at night (RGESN) |
| **Every minute and pushed with Reverb** (E4) | Departures change on the open page without polling | The most moving parts; E4 is not built |

Preferred: every minute while someone looks. **Against it:** "while someone looks" is a state to keep and test (when to start, when to stop, several workers); with the C extension, on demand costs the download alone (0.3 to 2 s) and needs none of it.

### 4. Decoding protobuf (ADR-0015)

| Option | For | Against |
|---|---|---|
| **Pure-PHP runtime** (current) | Nothing to build; Composer and Dependabot keep it current; the vehicle feed (57 KB) decodes fast enough | 1.2 s and 344 MB for trip updates: a worker needs a high `memory_limit`, and a click waits; decoding dominates the cost, not the download |
| **C extension, built with PECL** | 40 times faster, 30 times less memory; the same generated classes, Composer's runtime steps aside when the extension is loaded | Not packaged by Ubuntu 26.04: a build stage in the PHP image and a build on the server by Ansible (compiler, `php8.5-dev`); its version must follow the Composer runtime by hand (Dependabot does not see PECL); a native crash takes the process down |
| **C extension, built once and copied** (multi-stage image, the `.so` shipped to the server) | No compiler on the server or in the runtime image | The `.so` must match PHP's exact build and architecture (the server's and each workstation's); one more artifact to version |
| **Decode in another process** (a small service in Go, Rust or Python) | Fast, keeps PHP light | A second language and runtime to deploy and review, against ADR-0001's "plain Laravel" outside ingestion |
| **Avoid decoding**: a producer feed in JSON, or SIRI Lite | No protobuf at all | TBM publishes GTFS-RT only in protobuf; SIRI Lite would be a new driver (ADR-0001: only when a network needs it) |

Preferred: the C extension, built in the PHP image (a `protobuf` stage copied into `runtime`) and on the server by the same Ansible role, with a CI check that its version equals the Composer runtime's. It supersedes point 1 of ADR-0015 by a new ADR. **Against it:** ADR-0015 chose pure PHP knowing the extension existed, and the vehicle feed does not need it; if the stop page is never built, the extension costs a build step for nothing. The decision should wait for the story that reads trip updates.

### 5. The destination of a departure whose vehicle is not running yet

| Option | For | Against |
|---|---|---|
| **Static `trip_headsign`** | Set on 66,922 of 67,341 trips; written by the operator for riders, like the label of ADR-0018 | Needs `trips.txt` imported (6.8 MB a day); nothing for `nt_` trips |
| **Last stop of the trip update**, named from the stop catalogue | One source, `nt_` trips included | ADR-0018 found it unreliable for running vehicles at midday; not yet measured for trips not started |
| **None** ("Destination inconnue" until the vehicle runs) | Nothing to add | Departures grouped by destination (E3.7) would mostly fall under "Destination inconnue" |

Preferred: to measure first, on the recorded samples, whether the last stop of a trip not started is stable. **Against measuring:** the headsign exists for 99 % of trips and is what riders read; importing trips is also what E7.2 (stops of a line) needs.

## Interface

- **Vehicle:** "Prochains arrêts" in the popup (the next 3 to 5, times in minutes, a skipped stop said as such), and the same list in the text view behind a button, for RGAA.
- **Stop:** the departures in the popup of the station on the map (E7.1), grouped by line then destination like the text view (E3.7); each station on its own, never merged with its namesakes, which are mostly the stop across the street; cancelled trips and skipped stops not shown, TBM giving them no time. Decided on 2026-10-26 instead of a page per station.

## Open questions

- What the 777 "no data" stop times mean on TBM, and what the feed holds at night: the raw samples recorded since 2026-10-20 (outside the repository) will tell.
- Payload of a stop's departures and of a vehicle's next stops, to measure once the format is chosen.
