# Idea: next departures at a stop, next stops of a vehicle

- **Status:** Exploring
- **Related:** ADR-0001 (trip updates port), ADR-0018 (destination, in pull request #53), [network-on-map.md](network-on-map.md) step 5, E7.1, E7.2

## Why

The map shows where vehicles are; a rider then asks when the next one comes to their stop, and where the one in front of them stops next. Both answers are in GTFS-RT trip updates, which ADR-0001 kept for a third port "until next departures enter the PRD". The brief puts timetables out of the MVP: these features need its scope changed first.

## Measures (live TBM, recorded payload of 2026-10-20 at 15:35)

| Measure | Value |
|---|---|
| Feed (`/gtfsfeed/realtime/bordeaux`) | 2.7 MB, not compressed by the producer (0.85 MB gzipped); a new version every minute (27 in 27 min) |
| Trips | 2,041: 1,983 scheduled, 57 cancelled, 1 added |
| Stop time updates | 69,568: 68,324 with an absolute time (none with a delay only), 467 skipped stops, 777 without data |
| Horizon | trips up to 9 h ahead; per 10 min, about 2,400 upcoming stop times in the first hour, 1,150 in the second |
| Stops with a departure in the next hour | 3,125 of 5,233 platforms; every stop id of the updates is in the static stops |
| Running vehicles with an update | 420; their update keeps the stops already passed (median 14) and lists those left (median 22, up to 88) |
| Alerts in the same feed | none (alerts are a separate feed, `/gtfsfeed/alerts/bordeaux`) |

## Findings

- **Trip updates alone cover the rest of the day**, cancellations and skipped stops included. Next departures may not need the static stop times (193 MB, about 2 million rows, the open question of network-on-map.md step 5) while the feed is up; the static ones would only fill a feed failure or the night.
- **Stops already passed stay in an update**: they are filtered out by time, not by position in the list.
- **The cost is the reading**: 2.7 MB a minute is about 3.9 GB a day if read all the time, where ADR-0018 reads nothing for destinations (RGESN).

## Options

1. **Read on demand**: trip updates are read only when someone opens a stop or a vehicle, at most once a minute with the feed's `ETag`, indexed by stop and by trip in the cache store for that minute. Nothing is read at night or without visitors. Recommended to start.
2. **Read every minute in the background** and push changes with Reverb (E4): live departures without asking, but the full cost all day.
3. **Static stop times imported and corrected by trip updates**: full timetables, works without the live feed, but the heaviest import and storage, and it is the part the brief excludes.

## Interface

- **Vehicle:** "Prochains arrêts" in the popup (the next 3 to 5, times in minutes, a skipped stop said as such), and the same list in the text view behind a button, for RGAA.
- **Stop:** a server-rendered page per station (`/arrets/{station}`, 2,189 stations rather than 5,233 platforms), departures grouped by line then destination like the text view (E3.7), cancelled ones shown, not hidden. It needs a way to reach a stop: stations on the map (E7.1) or the line page (E7.2).

## Open questions

- The brief's scope: timetables are out of the MVP (maintainer's decision).
- The destination of a departure whose vehicle is not running yet: no label exists then; the last stop of its update, named from the stop catalogue, or the static `trip_headsign`. ADR-0018 found the last stop of a running vehicle's update unreliable at midday: to sample again for trips not started.
- What the 777 "no data" stop times mean on TBM, and what the feed holds at night: the raw samples recorded since 2026-10-20 (outside the repository) will tell.
- Payload of a stop's departures and of a vehicle's next stops, to measure once the format is chosen.
