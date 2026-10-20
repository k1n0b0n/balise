# Story 013: Group vehicles by line and destination

- **PRD:** E3.7
- **Status:** In progress, waiting for the evening and morning samples (resumed 2026-10-21)

## Acceptance criteria

See E3.7 in [prd.md](../prd.md).

## Tasks

- [x] Coverage, midday sample with a throwaway script outside the repository reading both live feeds: it disproved ADR-0017, superseded by ADR-0018 (see Notes)
- [ ] Coverage, evening and morning samples (raw payloads recorded since 2026-10-20 15:09 outside the repository, every 20 s for positions, 60 s for trip updates): positions with a label, label stable along a trip, positions without a `route_id` and how many trip updates fill (it decides whether trip updates are read at all)
- [x] Configuration: `label_is_destination` for TBM; a network without it shows no destination
- [x] Normalised position (ADR-0013): optional `destination` (`vehicle.label` as is, when the network says so); `vehicle_positions` gets `destination`, and `RecordPositions` counts a change of it as a change
- [ ] Only if positions without a line were sampled: a `trip_updates_url` for TBM; the `gtfs-rt` driver reads it when a position without a `route_id` names a trip it does not know, at most once a minute and with its `ETag`, the line cached per trip for a day; if the feed fails, positions are still recorded without a line and a warning is logged; `network:poll` reports the reads (count, bytes)
- [x] Positions payload: the destination; compressed size measured before and after on the same snapshot
- [ ] Text view: a heading per line (`h3`), then per destination (`h4`) sorted by name ignoring case with "Destination inconnue" last, then the vehicles named "Tram A - La Gardette", their number without a destination; each button keeps a distinct accessible name with the number; the server-rendered view and the module agree (done); capitals checked with VoiceOver (to do, by the maintainer)
- [x] Popup: the vehicle's name, its destination and its number
- [ ] Closing (workflow step 5, pull request #54): move the study of `ideas/line-and-destination.md` into the notes under `### Study` and delete it; close `ideas/vehicles-without-line.md` the same way from the samples; review `ideas/overview.md`
- [ ] Tests: driver (label copied only when configured; if kept, trip updates: cache, once a minute, `ETag`, failure, `route_id` fallback), payload, text view headings, order and names; browser test with axe

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

### Payload (RGESN)

Same live snapshot of 384 vehicles (2026-10-20, 15:15), all with a destination: the full positions payload goes from 9,350 to 11,255 bytes gzipped (+20 %; 55 to 68 KB raw). Only the first request of a page carries it whole; later ones carry the changed vehicles only, so a destination costs about 5 bytes compressed per vehicle sent. ADR-0017 had measured +12 % for stop names at night, on shorter signs.
