# ADR-0022: Feed recordings kept on the server, one public archive per service day

- **Status:** Accepted
- **Date:** 2026-10-25

## Context

- Replay (ADR-0001, E5.2) needs recorded feeds: TBM is nearly empty at night, which is when the maintainer develops, and a load test (E5.3) needs a feed that does not depend on the hour.
- A one-off sample ages: the provider changes its lines and trips at least twice a year, and positions recorded under one catalogue show unknown lines under the next. Recordings must keep the catalogue they were made with.
- The prototype (`record.sh`, outside the repository, story 013) recorded from the maintainer's workstation and stopped whenever it was switched off. Its numbers for TBM: 4,311 vehicle payloads on a weekday, 62 MB gzipped; 24 MB for the static GTFS; 715 MB a day for trip updates.
- TBM's network empties between about 01:30 and 04:30 (the feed carries no vehicle then): a calendar day would cut the evening's last trips from their day.
- The data is open (Licence Ouverte), so redistributing it needs only the attribution.

## Decision

1. **The poll records what it already reads**, with no request of its own: each new vehicle payload, as received, gzipped, in a Docker volume of the server. The catalogue import keeps its last downloaded archive beside it. Trip updates and alerts are not recorded: no story reads them.
2. **One recording per service day**, from the network's first vehicle to its last: a day ends when no fresh vehicle has been seen for 30 minutes, and is named after the date it began. A network that never empties is cut at a configured hour (04:00 for TBM). Feeds without a fresh vehicle are not kept.
3. **Each night, finished days become archives**: one uncompressed tar per network and day (the payloads are gzipped already), with the catalogue, a manifest and a README giving the source, the licence and the attribution; the raw files are then deleted. The run takes every finished day not yet archived, so a run missed during the host's maintenance is caught up the next night. The time is configured per server, outside the host's maintenance window.
4. **The last 30 archives per network are kept**, about 2.6 GB for TBM.
5. **The archives are public**, with their list, under `/recordings/`, served by the web container's nginx from a second, read-only volume, without PHP: one download at a time per address, at 5 MB/s, and disallowed to robots.
6. **Off by default**, on in production through the server's environment.

## Alternatives considered

| Option | Why not |
|---|---|
| A fixed sample in a GitHub release | Ages with the provider's catalogue; recording again by hand twice a year |
| Archives pushed to GitHub releases or S3 by the server | A token or an account on the server for data the server can serve itself |
| Download behind a token | The data is open; the only risk is bandwidth, which the rate and connection limits bound |
| Calendar days, cut at midnight | Splits the evening's last trips from their day; a fixed 03:00 cut assumes a timetable the network may not keep |
| Record trip updates too | Ten times the size, a second request a minute to the provider, and no consumer until the trip updates port |

## Consequences

- Any developer can replay yesterday's network: `/recordings/index.json` lists what there is.
- The server stores up to about 3 GB more, on two new volumes kept across deploys; losing them loses only past recordings.
- The poll writes one small file every 20 s while the network runs; a failed write is logged and never fails the poll.
- Replay (E5.2) imports the archive's catalogue, so a recording stays playable after the provider changes its lines.
