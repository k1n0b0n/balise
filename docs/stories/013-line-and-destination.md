# Story 013: Group vehicles by line and destination

- **PRD:** E3.7
- **Status:** Draft

## Acceptance criteria

See E3.7 in [prd.md](../prd.md).

## Tasks

- [ ] Coverage sampled before any code, morning, midday and evening, with a throwaway script outside the repository reading both live feeds: share of positions whose trip has an update, of updates without a last stop, and whether a trip's last stop changes from one sample to the next (it decides the per-trip cache)
- [ ] Configuration: a `trip_updates_url` for TBM next to the positions URL; a network without one shows no destination
- [ ] `gtfs-rt` driver: when a position names a trip it does not know, it reads the trip updates feed, at most once a minute and with its `ETag`; each trip's line and last stop are cached for a day in the cache store; if the feed fails, positions are still recorded, without destination, and a warning is logged
- [ ] Normalised position (ADR-0013): optional `destinationId` (namespaced stop id) and `label` (`vehicle.label` as is); a position without a `route_id` takes its trip update's; `vehicle_positions` gets `destination_id` and `label`, and `RecordPositions` counts a change of either as a change
- [ ] `network:poll` reports the trip update reads (count, bytes) and the positions with a destination, to measure the frequency and volume over a day
- [ ] Positions payload: the destination's name, joined from the stop catalogue, and the label; compressed size measured before and after on the same snapshot
- [ ] Text view: a heading per line (`h3`), then per destination (`h4`) sorted by name with "Destination inconnue" last, then the vehicles named "Tram F - AEROPORT", their number without a label; each button keeps a distinct accessible name with the number; the server-rendered view and the module agree
- [ ] Popup: the vehicle's name, its destination and its number
- [ ] Tests: driver (cache, once a minute, `ETag`, failure, `route_id` fallback), payload, text view headings and names; browser test with axe

## Notes

Decisions taken during the story, links to ADRs, what was learned.
