# Idea: vehicles without a line

- **Status:** Exploring
- **Related:** ADR-0013, story 009

## Observed

- Live TBM at 04:11 on 2026-10-18: one vehicle, a tram on trip `nt_268436716`, with an empty `route_id`; the trip is not in the static GTFS of 2026-10-17. Probably an unplanned run (depot, test, replacement), not checked with TBM.
- ADR-0013 measured `route_id` on 100 % of 407 TBM vehicles at midday on 2026-10-16.

## To investigate

1. Over a full day, sample the feed (every hour, for instance) and count the vehicles without a known line, by cause:
   - `route_id` empty, trip absent from the static GTFS (`nt_` or other prefixes);
   - `route_id` empty, trip present in the static GTFS (the line can be found from the trip, ADR-0013 decision 6);
   - `route_id` set but unknown to the catalogue (catalogue out of date, or a line the import drops);
   - no trip descriptor at all.
2. Check whether `nt_` trips account for all of them, and what they are: moving or parked, which modes, at which hours.
3. Ask TBM, or look for documentation of the `nt_` prefix, if the meaning stays unclear.

## Then decide

Show them as today ("Ligne inconnue"), find their line from the trip, show them differently (for example "hors service"), or hide them; hiding needs ADR-0013 amended, since it says a vehicle without a line is shown, never hidden.
