# Story 032: Replay a recorded service day locally

- **PRD:** E5.2
- **Status:** Done

## Acceptance criteria

See E5.2 in [prd.md](../prd.md); the recordings come from [story 031](031-feed-recordings.md) and [ADR-0022](../adr/0022-feed-recordings.md).

## Tasks

- [x] `GtfsRealtimeDecoder`: the GTFS-RT decoding split from the HTTP request, shared by the live driver and the replay
- [x] `ReplayFeed`: the payload recorded at the replayed time, decoded as live, positions and the feed's time moved to now; the payload's name as its ETag, so an unchanged payload answers as a `304`
- [x] `Replays`: the replay in progress (day, start time, when it started), the replayed time (at the recorded pace, in a loop from the day's first payload to its last plus one interval), the download from `/recordings/` of the site in the repository's *Website* field on GitHub or `RECORDINGS_SOURCE_URL`
- [x] `network:replay {network} {day?} {--from=12:00} {--stop}`: downloads the day when it is not there, imports its catalogue, starts; `--stop` goes back to the live feed and imports the live catalogue again; refused in production
- [x] `VehicleFeeds` builds the replay while one runs; the daily import leaves a replaying network's catalogue alone
- [x] The map page says "Rediffusion du {jour} : la carte montre le réseau de {heure} ce jour-là, décalé à l'heure actuelle"; the time moves on every 30 s in the browser, without a live region
- [x] Tests: the payload of the replayed time moved to now, the `304`, the loop, the download of the latest day with its catalogue, no source and an unknown day, `--stop`, production refused, the import left alone, the banner and its absence

## Notes

- **No production address in the repository** (AGENTS.md): the command asks GitHub for the repository's *Website* field and adds `/recordings/`; `RECORDINGS_SOURCE_URL` overrides it. Without either, it says what to set.
- **Times moved to now, nothing else changed.** Played as recorded, every position would be days old and dropped (ADR-0013). The replay moves each position and the feed's time by the gap between now and the replayed time, which stays the same for the whole loop, so the feed keeps its 20 s rhythm: the poll's wait (E2.5) and `Next-Version-At` (E4.1) follow it as they follow the live feed. A replayed clock for the whole application (sessions, caches, the browser's `Date`) or a replay mode in the single write path (ADR-0002) were the alternatives.
- **The catalogue of the day.** The archive's catalogue replaces the live one, with no ETag, so that `--stop` and the next live import download the live one in full; the daily import skips a replaying network, which would otherwise put today's lines back under the replayed day's positions.
- **Tried locally** on the prototype's recording of Wednesday 2026-10-21 (4,311 payloads, laid out as an archive): at 23:21, 418 vehicles of that day's noon on the map, the banner and `Next-Version-At` as live. The poll read the replayed feed about 9 s after its publication rather than 1 s: the prototype named its files after the time it downloaded them, 5 to 7 s after the feed's own time; the server's archives use the feed's time.
- **32-bit coordinates**: protobuf keeps coordinates as 32-bit floats, so the tests compare them rounded.
