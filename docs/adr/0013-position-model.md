# ADR-0013: Normalised vehicle position

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

- ADR-0001 asks for an app-owned position model, GTFS-inspired, with ids namespaced per network and optional fields where standards differ, linkable to a trip for later next departures.
- The MVP needs: a point on the map (E3.1), its line for colour and number (E3.2), the vehicle id and the age of the position (E3.3, E3.5), the latest state per vehicle without stale positions (E2.3).
- RGAA (ADR-0006): no information by colour alone, so a vehicle's line is shown as text as well.

### What French feeds actually send (2026-10-16)

A survey of 83 GTFS-RT VehiclePositions feeds listed on transport.data.gouv.fr (4,387 vehicles):

| Finding | Feeds | Examples |
|---|---|---|
| TBM fills vehicle id, position, timestamp, `route_id` and `trip_id` for 100 % of 407 vehicles; `vehicle.label` holds the destination, not a fleet number | — | TBM |
| `route_id` missing but `trip_id` present: the line can be found in the static GTFS | 11 | Fil Bleu (Tours), Rubis (Bourg-en-Bresse), Renfe AVE |
| Neither `route_id` nor `trip_id` (1 % of vehicles): no trip descriptor at all, yet most are moving between stops or show a destination as label, so they are not simply out of service | 8 | Linead (Dreux) 11/11, Sète, Ametis, Aléop |
| `vehicle.id` missing | 10 | beeMob, Qub, Mobivie |
| Position `timestamp` missing | 2 | Bibus (Brest), Renfe AVE |
| Timestamps up to 86 s in the future (producer clock skew) | 1 | Mistral (Toulon) |

TBM ages: median 9 s, 90 % under 49 s, 25 of 403 vehicles older than 5 minutes, the oldest above 2 hours. GTFS-RT has no "out of service" flag.

## Decision

1. **A position is a `readonly` value object owned by the ingestion port** (`VehicleFeed`, ADR-0001):

   | Field | Type | Required | GTFS-RT source | SIRI-VM |
   |---|---|---|---|---|
   | `vehicleId` | string, namespaced (`tbm:ineo-bus:2378`) | yes | `vehicle.id`, else `vehicle.label`, else the entity id; the source is chosen per network | `VehicleRef` |
   | `latitude`, `longitude` | float, WGS 84 | yes | `position` | `VehicleLocation` |
   | `recordedAt` | UTC instant | yes | position `timestamp`, else the feed header timestamp | `RecordedAtTime` |
   | `lineId` | string, namespaced (`tbm:901`) | no | `trip.route_id`, else the line of `trip.trip_id` in the catalogue | `LineRef` |
   | `tripId` | string, namespaced | no | `trip.trip_id` | `DatedVehicleJourneyRef` |

2. **Storage keeps the latest position per vehicle:** table `vehicle_positions`, primary key `vehicle_id`, the fields above plus `received_at` (ingestion time), written by upsert. No `vehicles` table while a vehicle has no attribute of its own; no foreign key on `line_id`, since a feed can name a line before the catalogue knows it.
3. **One write path** (ADR-0002) upserts a batch, compares it with the stored state and emits `PositionsUpdated` with the changed positions only (ADR-0006).
4. **Freshness filter (E2.3):** a position is dropped when `recordedAt` is more than **5 minutes** before `received_at`, or more than **2 minutes** after it (clock skew). Both limits are per-network settings.
5. **A vehicle without a line is shown, not hidden:** neutral colour and the text "Unknown line" on the map, its own group in the text view (E3.5). The information stays in text, as RGAA requires, and a network whose producer never sends trips is not emptied.
6. **Finding the line from the trip** is part of the model but built only when a network needs it (E6); TBM sends `route_id`.

## Alternatives considered

| Option | Why not |
|---|---|
| Line required, vehicles without one dropped | Empties networks like Linead whose producer omits trips, and hides vehicles that are really running |
| Store every GTFS-RT field (bearing, speed, odometer, stop, status, destination) | No MVP story uses them; each is one column and one adapter line when a story needs it, and replay keeps the raw payloads (E5.1) |
| Mirror the GTFS-RT `VehiclePosition` message | Ties the app to one standard (ADR-0001) |
| A `vehicles` table now | Nothing to store besides the id in M1 |
| Reject any future timestamp | Drops a whole network whose producer clock runs a minute ahead |

## Consequences

- Every driver must produce the three required fields, with the fallbacks above; tests cover each fallback with a recorded payload.
- An entity id that changes between fetches creates short-lived ghost vehicles, removed by the freshness filter after 5 minutes.
- E3.2 and E3.5 handle "Unknown line" from the start.
- *Later*: cross-check with GTFS-RT TripUpdates to give lineless vehicles their trip and line; bearing arrows and destination labels when a story asks for them.
