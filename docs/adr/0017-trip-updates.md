# ADR-0017: Line and destination of a position from GTFS-Realtime trip updates

- **Status:** Superseded by [ADR-0018](0018-destination-from-vehicle-label.md)
- **Date:** 2026-10-18

## Context

- E3.7 groups the vehicles of the text view by line, then by destination, and shows the destination in the popup ([story 013](../stories/013-line-and-destination.md#study)).
- TBM's vehicle positions carry no usable destination field, and up to 14 of 37 trams at midday have no line: their `nt_` trips (unplanned) have an empty `route_id` and are absent from the static GTFS (ADR-0013 shows them as "Ligne inconnue").
- TBM publishes GTFS-Realtime trip updates (`/gtfsfeed/realtime/bordeaux`). Two samples, live:

  | | 2026-10-18 12:40 | 2026-10-18 23:32 |
  |---|---|---|
  | Trips in the feed | 986, 38 `nt_` | 295, 13 `nt_` |
  | `nt_` trips with a `route_id` | 38 of 38 | 13 of 13 |
  | Positions whose trip has an update | — | 118 of 119 |
  | Updates whose last stop is missing | — | 5 of 295, none of them for a running vehicle |
  | Feed size (protobuf, the producer does not compress) | — | 358 KB, 125 KB if gzipped |

- The last stop of a trip's updates is its terminus, named in the stop catalogue (62 of 62 at 23:32). A depot run shows its real end ("MECA"), a short run its short terminus: what the vehicle actually does.
- TBM also puts the destination sign in `vehicle.label` (117 of 117 at 23:32: "AEROPORT", "C.CIAL BORDEAUX LAC"), in capitals and abbreviated, and the GTFS-RT reference defines that field as the vehicle's own label: other networks put a fleet number there (ADR-0013).
- ADR-0001 kept a third port for trip updates until next departures enter the PRD: predicted times, cancellations, skipped stops. E3.7 needs none of that, only two facts per trip.
- In SIRI-VM, a position carries its line and destination itself (`LineRef`, `DestinationRef`, `DestinationName`): destination belongs with the position, whatever the standard.

## Decision

1. **The destination is an optional field of the normalised position** (ADR-0013): a stop id, namespaced like the others. A position without one is shown under "Destination inconnue".
2. **The `gtfs-rt` driver of `VehicleFeed` reads the trip updates feed**, a second URL of the same standard in the network's configuration. For each position, its trip's update gives the destination (the last stop of the update) and, when the position has no `route_id`, its line. No new port: the third port of ADR-0001 stays for next departures.
3. **Trip updates are fetched only when needed**: when a position names a trip not yet known, at most once a minute, with the feed's `ETag`. What a trip gives is cached for the trip's lifetime (a day at most), so most polls read only positions. The real frequency and volume are measured in the story (RGESN).
4. **The positions payload carries the destination's name**, not its stop id: measured on 119 vehicles at 23:32, the name adds 364 bytes compressed (+12 %), the stop id 488 bytes and would need the names from another endpoint.
5. **The vehicle's own label is kept and named in the interface** (maintainer's choice): `vehicle.label` becomes an optional field of the position, stored and sent as is. A vehicle reads "Tram F - AEROPORT" (mode, line, label) instead of its id ("ineo-tram:1302"), which riders never see; without a label, its number stays. Vehicles of the same line and sign would share that name, so the text view's buttons add the number for screen readers (RGAA: distinct names), and the popup shows it.
6. **Coverage is checked in the story** over a day (morning, midday, evening): share of positions with an update, of updates without a last stop, and stability of the destination along a trip. If it falls short, the static `trip_headsign` (option 3 of the idea) is the fallback, by a new ADR.

## Alternatives considered

| Option | Why not |
|---|---|
| `vehicle.label` as destination (TBM) | Free and what riders read on the vehicle, but specific to TBM (a fleet number elsewhere), in capitals and abbreviated; and trip updates are still needed for the line of `nt_` trips. Kept as the vehicle's name instead (decision 5), and as a per-network option for the destination if trip updates prove too costly |
| A `TripUpdates` port now, merged outside the ingestion layer | The shape of next departures (predicted times per stop) is not known yet; building it for two fields is speculative |
| Static `trip_headsign` from `trips.txt` | No answer for `nt_` trips, which are a third of the trams at midday; needs trips imported |
| Fetching trip updates on every poll | 358 KB to 1.2 MB every 20 s for facts that change once per trip |
| The destination's stop id in the payload | Larger once compressed (measured above), and the browser would need the stop names too |

## Consequences

- The `gtfs-rt` driver gets a second URL and a cache; `VehiclePosition` gets an optional destination and label; positions store the destination's stop id and the label.
- On a network whose label is a fleet number, a vehicle reads "Bus 12 - 4021": still more telling than an internal id.
- The line of `nt_` trips becomes known: "Ligne inconnue" should become rare on TBM.
- A network without trip updates simply shows no destination.
- Next departures will need the full trip updates (times per stop): the third port of ADR-0001 is still to be designed then, and may take over the fetching decided here.
