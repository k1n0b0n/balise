# Story 013: Group vehicles by line and destination

- **PRD:** E3.7
- **Status:** In progress, waiting for the VoiceOver check of capitals

## Acceptance criteria

See E3.7 in [prd.md](../prd.md).

## Tasks

- [x] Coverage, midday sample with a throwaway script outside the repository reading both live feeds: it disproved ADR-0017, superseded by ADR-0018 (see Notes)
- [x] Coverage, evening and morning samples (raw payloads recorded since 2026-10-20 15:09 outside the repository, every 20 s for positions, 60 s for trip updates): positions with a label, label stable along a trip, positions without a `route_id` and how many trip updates fill (see Notes)
- [x] Configuration: `label_is_destination` for TBM; a network without it shows no destination
- [x] Normalised position (ADR-0013): optional `destination` (`vehicle.label` as is, when the network says so); `vehicle_positions` gets `destination`, and `RecordPositions` counts a change of it as a change
- [x] ~~Trip updates for the line of positions without a `route_id`~~: deferred to the trip updates port (maintainer's decision, 2026-10-21, moved to the PRD's *Later*); the samples found 283 such positions in 20 h (0.03 %), still shown as "Ligne inconnue"
- [x] Positions payload: the destination; compressed size measured before and after on the same snapshot
- [ ] Text view: a heading per line (`h3`), then per destination (`h4`) sorted by name ignoring case with "Destination inconnue" last, then the vehicles named "Tram A - La Gardette", their number without a destination; each button keeps a distinct accessible name with the number; the server-rendered view and the module agree (done); capitals checked with VoiceOver (to do, by the maintainer)
- [x] Popup: the vehicle's name, its destination and its number
- [x] Closing (workflow step 5, pull request #54): the studies of `ideas/line-and-destination.md` and `ideas/vehicles-without-line.md` moved into the notes under `### Study`, both files deleted; `ideas/overview.md` reviewed
- [ ] Tests: driver (label copied only when configured), payload, text view headings, order and names; browser test with axe

## Notes

### Coverage, midday sample (2026-10-20, 12:17 to 12:31 Paris)

Trip updates are at `…/gtfsfeed/realtime/bordeaux`: 3 MB, not compressed, with an `ETag`, read in about 2 s.

| Measure | Value |
|---|---|
| Positions whose trip has an update with a last stop | 382 to 391 of 389 to 398 (98 %) |
| Updates without a last stop | 39 to 55 of about 2,220 |
| Positions without `route_id` | 0 (no `nt_` trip at midday) |
| Positions with a `vehicle.label` | 100 %; every label is a destination name ("CENON GARE", "Carle Vernet"), not a number |
| Running vehicles whose update's last stop changed within a minute | 4 to 8 of about 378 |

**The last stop of an update is not the terminus at midday.** For running buses and trams, the last stop advances along the route as the vehicle moves: line 911 went 606 → 6851 → 9813 (Chantiers de la Garonne → Cenon Gare), tram 3774 → 3776 (Place de la Bourse → Quinconces). The 62 of 62 of ADR-0017 was measured at 23:32, when the remaining trips were short. Meanwhile `vehicle.label` already names the destination, matching the update's terminus once reached (label "CENON GARE", last stop "Cenon Gare (Tram A)").

Consequence: ADR-0017's premise (the destination from the update's last stop) does not hold, and the per-trip cache for a day would freeze an intermediate stop. Decided in [ADR-0018](../adr/0018-destination-from-vehicle-label.md): the label is the destination, shown as is (bus signs in capitals, "C.CIAL", "GARE ST-JEAN MECA" for a depot run), and trip updates are kept only for the line of positions without a `route_id`, if the evening and morning samples show any.

### Coverage, evening and morning samples (2026-10-20 15:09 to 2026-10-21 11:10 Paris)

Throwaway scripts outside the repository decoded every recorded payload: 3,601 snapshots of positions, about 1,200 of trip updates. Positions more than 5 min old or 2 min ahead were dropped first, as the app does (51,980 of 960,585).

| Measure | Value |
|---|---|
| Positions kept | 908,605 (none from 02:00 to 04:00: the feed is empty) |
| Positions with a `vehicle.label` | 908,322; the 283 others are exactly those without a `route_id` |
| Trips whose label changed along the way | 0 of 5,644 |
| Positions without a `route_id` | 283 (0.03 %), on 9 `nt_` trips, all trams: 3 around 17:45, 1 at 19:35, 2 around 21:20, 2 around 07:00 to 08:00, 1 at 10:45; 2 to 72 positions each |
| Of those, filled by the latest trip updates | 283 of 283 (routes `60`, `61`, `62`); no destination there either, the label being empty |
| Positions without a trip, or with a `route_id` absent from `routes.txt` | 0 |
| Trip updates stored, gzipped | about 26 MB an hour, against 2.4 MB for positions |

The label holds as the destination (ADR-0018). The line of the `nt_` trips is in the trip updates, but reading a feed ten times the size of positions for 0.03 % of them is deferred to the trip updates port, where next stops and departures will share the same read (PRD *Later*). The 14 of 37 trams without a line of 2026-10-18 were a Sunday; the recording runs until 2026-10-27, so a weekend can be checked when the port is planned.

### Payload (RGESN)

Same live snapshot of 384 vehicles (2026-10-20, 15:15), all with a destination: the full positions payload goes from 9,350 to 11,255 bytes gzipped (+20 %; 55 to 68 KB raw). Only the first request of a page carries it whole; later ones carry the changed vehicles only, so a destination costs about 5 bytes compressed per vehicle sent. ADR-0017 had measured +12 % for stop names at night, on shorter signs.

### Study

The two ideas studied before and during the story (moved from `ideas/line-and-destination.md` and `ideas/vehicles-without-line.md`).

#### Vehicles grouped by line and destination

The text view grouped vehicles by line only; a rider looks for a line *and* a direction. Grouping is hierarchical: a heading per line, then a sub-heading per destination within it (`h3` then `h4`, RGAA 9.1), then the vehicles.

Measured on live TBM, 2026-10-18 at 12:40: trip updates (`/gtfsfeed/realtime/bordeaux`) had 986 trips, 38 of them `nt_`, all 38 with a `route_id` and the same trip ids as the positions; the last stop of a trip's updates looked like its terminus; static `trips.txt` has `trip_headsign` for planned trips, nothing for `nt_` trips.

Options for the destination: the static headsign (needs trips imported, 5.2 MB uncompressed, no answer for `nt_` trips); the last stop of the trip updates (one source, planned by ADR-0017); both. The midday sample above disproved the second, and ADR-0018 took `vehicle.label` instead, an option the study had not listed.

#### Vehicles without a line

Live TBM at 04:11 on 2026-10-18 showed a tram on trip `nt_268436716` with an empty `route_id`, absent from the static GTFS, and ADR-0013 had measured `route_id` on 100 % of 407 vehicles at midday on 2026-10-16. The idea asked to count, over a day, the vehicles without a known line by cause (empty `route_id` with a trip absent from or present in the static GTFS, `route_id` unknown to the catalogue, no trip at all), then decide how to show them.

Answered by the samples above: `nt_` trips are the only cause, all trams, a few a day on weekdays. They stay shown as "Ligne inconnue" (ADR-0013); their line from the trip updates waits for the trip updates port. Asking TBM what `nt_` means was not needed.
