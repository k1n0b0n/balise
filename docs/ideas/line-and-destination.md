# Idea: vehicles grouped by line and destination

- **Status:** Planned → E3.7, decided in ADR-0017 (option 2)
- **Related:** ADR-0001 (trip updates port), ADR-0013, E3.5

## Why

The text view groups vehicles by line only; a rider looks for a line *and* a direction. Grouping is hierarchical: a heading per line, then a sub-heading per destination within it (`h3` then `h4`, RGAA 9.1), then the vehicles. Live TBM at midday on 2026-10-18 also showed up to 14 of 37 trams without a line: their positions carry an `nt_` trip, absent from the static GTFS, with an empty `route_id` ([vehicles-without-line.md](vehicles-without-line.md)).

## Measures (live TBM, 2026-10-18 at 12:40)

- TripUpdates (`/gtfsfeed/realtime/bordeaux`): 986 trips, 38 of them `nt_`, **all 38 with a `route_id`**, and the same trip ids as the positions (`nt_268436916`, line B).
- The last stop of a trip's stop time updates is its terminus: `nt_268436934` (line A) ends at Aéroport, `nt_268436916` (line B) at Berges de la Garonne.
- Static `trips.txt` has `trip_headsign` for planned trips (for line A today: La Gardette, Pin Galant, and short runs such as Porte de Bourgogne), nothing for `nt_` trips.

## Options for the destination

1. **Static headsign** (`trips.txt`): needs trips imported (5.2 MB uncompressed for TBM); no answer for `nt_` trips.
2. **Last stop from TripUpdates**, named from the stop catalogue: one source for planned and unplanned trips, and the same feed gives `nt_` trips their line. Needs the trip updates port of ADR-0001, a second poll, and an ADR.
3. Both: headsign first, TripUpdates for the rest. More code for little gain if option 2 holds over a day.

Option 2 is preferred, to be confirmed by sampling both feeds over a day (with the investigation of [vehicles-without-line.md](vehicles-without-line.md)).

## Open questions

- Payload (RGESN): send a destination stop id per position plus the names of the destinations in use, or the name itself? Measure both.
- A trip that ends at a short-run stop shows that stop: fine for riders, since it is where the vehicle really goes.
- Vehicles with no trip update: grouped under "Destination inconnue", last within their line.
