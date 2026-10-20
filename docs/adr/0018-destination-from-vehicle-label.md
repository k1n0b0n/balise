# ADR-0018: Destination of a position from the vehicle's label

- **Status:** Accepted
- **Date:** 2026-10-20
- **Supersedes:** [ADR-0017](0017-trip-updates.md)

## Context

- ADR-0017 took a vehicle's destination from the last stop of its trip's updates, cached for the trip's lifetime, on a sample taken at 23:32, when the remaining trips were short (62 of 62 ended at their terminus).
- The midday sample of story 013 (2026-10-20, 12:17 to 12:31) disproves it: for running vehicles, the last stop of an update advances along the route as the vehicle moves (line 911: Chantiers de la Garonne, then Cenon Gare (Parc Demi-Lune), then Cenon Gare (Tram A); tram D: Place de la Bourse, then Quinconces), for 4 to 8 of about 378 vehicles each minute. A per-trip cache would freeze an intermediate stop; reading the 3 MB feed on every poll would cost about 13 GB a day.
- The same sample: `vehicle.label` is set on every position (383 of 383) and is always the destination sign, never a fleet number: 97 distinct values such as "La Gardette", "Carle Vernet" (trams, mixed case), "ARTIGUES BLANCHERIE", "BOULIAC C.CIAL", "GARE ST-JEAN MECA" (buses, capitals and abbreviated; "MECA" is a depot run). Once a vehicle reaches the last stops, its update's last stop matches its label ("CENON GARE" and Cenon Gare (Tram A)).
- The GTFS-RT reference defines `vehicle.label` as the vehicle's own label: other networks put a fleet number there, so reading it as a destination is a property of the network, not of the standard.
- No position lacked a `route_id` at midday; on 2026-10-18, `nt_` trips did (14 of 37 trams), and their trip updates had one (38 of 38).

## Decision

1. **The destination is an optional text field of the normalised position** (ADR-0013), shown as is: no stop id, no join with the stop catalogue, no change of case (maintainer's choice). A position without one is shown under "Destination inconnue".
2. **A network says in its configuration whether its `vehicle.label` is a destination** (`label_is_destination`, true for TBM). The `gtfs-rt` driver then copies the label into the destination; otherwise a position has no destination.
3. **A vehicle is named by its mode, line and destination** ("Tram A - La Gardette", "Bus 01 - ARTIGUES BLANCHERIE"), its number without a destination; the text view's buttons add the number to stay distinct (RGAA), and the popup shows it. This keeps ADR-0017's decision 5, the label being the destination on TBM.
4. **Trip updates are read only for the line of a position without a `route_id`**, keeping ADR-0017's rules: only the ingestion layer reads them, at most once a minute, with the feed's `ETag`, the line cached per trip (it does not change along a trip). If the evening and morning samples of the story show no such position, this point is dropped from the story and trip updates are not read at all.
5. **Coverage is still checked in the story** over a day: share of positions with a label, stability of a vehicle's label along its trip, positions without a `route_id` and how many trip updates fill.

## Alternatives considered

| Option | Why not |
|---|---|
| Last stop of the trip updates (ADR-0017) | Not the terminus for running vehicles at midday; correct only with a full read every poll (about 13 GB a day) |
| Static `trip_headsign` from `trips.txt` | Needs trips imported (5.2 MB uncompressed for TBM), no answer for `nt_` trips |
| Labels in title case | "C.cial", "Ba 106": abbreviations break; the sign as the operator writes it is what riders read on the vehicle |
| Destination from the label on every network | Wrong on networks whose label is a fleet number: they would show "Bus 12 - 4021" grouped under a destination "4021" |

## Consequences

- No second feed on most polls, no destination cache, no stop name join: the destination costs only its own bytes in the payload, measured in the story (RGESN).
- Bus destinations read in capitals; sorting destinations ignores case. Screen reader behaviour with capitals is checked with VoiceOver in the story.
- A network whose label is not a destination shows none until a standard source (trip headsign, SIRI `DestinationName`) is added for it; SIRI-VM gives it with the position, like here.
- Next departures will still need the full trip updates: the third port of ADR-0001 is unchanged.
