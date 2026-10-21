# Ideas overview: shared foundations and order

- **Status:** Exploring
- **Date:** 2026-10-20
- **Covers:** every file of `docs/ideas/`, the PRD's E5 to E7 and *Later*; reviewed at the closing of every story (workflow step 5). Done ideas live in their stories: line catalogue in [story 009](../stories/009-text-view.md#study), line and destination and vehicles without a line in [story 013](../stories/013-line-and-destination.md#study)

## Where each idea stands

| Idea | State | Waits for |
|---|---|---|
| [network-on-map.md](network-on-map.md) | E7.1 stations, E7.2 line pages, no criteria yet; shapes and selection done and moved to stories 011 and 012 | Trips and stop order imported |
| [next-departures.md](next-departures.md) | Exploring | The brief's scope, a trip updates port |
| PRD *Later* | History stages 2 and 3, PostGIS, bearing arrows, second network (E6) | — |

## Foundations shared by several ideas

| Foundation | Serves | Cost | Verdict |
|---|---|---|---|
| **Raw payload recording** (E5.1), prototyped outside the repository since 2026-10-20 | Every measurement of every idea, replay at night (E5.2), load test (E5.3), offline tests | About 1.3 GB a day gzipped while it runs; a command and a retention rule | **Most favourable**: already proven, unblocks the rest, and is in the PRD |
| **Trips imported** (`trips.txt`, 6.8 MB a day: line, direction, headsign, shape) | Line of a position from its trip (ADR-0013 decision 6, vehicles without line), headsign of future departures, one representative trip per direction (E7.2) | One table rebuilt by the daily import | **Favourable**: small, three consumers |
| **Stop order streamed, not stored** (`stop_times.txt` read once per import, only the representative trips kept) | Stops of a line in order (E7.2), next stops of a vehicle when the live feed has none | Reading 193 MB at import; storing a few thousand rows | **Favourable** against storing 2.6 million rows, which only full timetables would need |
| **Stations** (`location_type=1`, 2,189 for TBM) | Stations on the map (E7.1), stop pages, departures grouped by station | One more catalogue entity, 36 KB gzipped to the browser | **Favourable**, after trips |
| **Trip updates port** (ADR-0001's third port) | Next stops of a vehicle, next departures, line of `nt_` trips (PRD *Later*: 0.03 % of positions, story 013), delays per line (history stage 3) | A second feed of 2.7 MB a minute, a reading strategy, a cache | **Favourable once one consumer is in the PRD**: then the others come almost free |
| **C extension `protobuf`** | Decoding trip updates (1.2 s and 344 MB in pure PHP, 0.03 s and 11 MB with it), replay at speed, larger networks (E6) | A build stage in the image and on the server, a version kept in step with Composer by hand | **Favourable with the trip updates port, not before** |
| **Reverb** (E4) | Positions pushed instead of polled, departures pushed | A process, a socket fallback (E4.2) | Independent of the ideas above; already planned |
| **PostGIS** | Stops and vehicles around me, viewport filtering | An extension, a migration to `geography` (ADR-0002) | **Not needed yet**: 2,189 stations filter in the browser; only "around me" needs it |

## Where one choice serves several ideas better

1. **Reading trip updates "while someone looks" over "on demand"** once there are several consumers: next stops, departures and `nt_` lines share one read a minute; delays per line (history stage 3) would need it always on, which RGESN argues against until that story exists.
2. **Importing trips and streaming the stop order** over storing stop times: it answers E7.2, the headsign and the line of a trip without the volume of full timetables, which the brief excludes.
3. **The next stops of a vehicle before the next departures at a stop**: the same port, but it opens from the existing popup and text view, with no station, no stop page and no change to the brief beyond the live feed. It is the cheapest first consumer of the port.
4. **Recording before anything else**: every open question of these ideas (night feed, `nt_` trips, stability of a trip's last stop, "no data" stop times) is answered from recorded payloads, not live sampling at fixed hours.

## Proposed order

1. E5.1, recording in the repository (from the prototype), then E5.2 replay.
2. Trips import with the stop order of representative trips; then E7.2 line pages and E7.1 stations.
3. Trip updates port with the C extension (one ADR superseding ADR-0015 point 1), first for the next stops of a vehicle, then for departures at a station once the brief's scope is opened.
4. E4 and history when wanted; PostGIS with "around me".

## Against this order

- The MVP is live (E1.3a to E1.3e, stories 014 to 018); E1.3f, E1.4 and E1.5 are open. Each feature now reaches riders, not only reviewers of the repository.
- Each foundation adds to what a reviewer reads in ten minutes; two of them (C extension, trip updates port) change ADRs.
- Next departures is the idea riders would value most, and this order puts it last.
