# Idea: the network on the map and in text

- **Status:** Planned → E7.1, E7.2; next departures studied in [next-departures.md](next-departures.md). Done and moved: whole-network shapes ([story 011](../stories/011-network-shapes.md#study)), selected line ([story 012](../stories/012-selection.md#study))
- **Related:** ADR-0001, ADR-0002, ADR-0006

## Data (TBM static GTFS of 2026-10-17)

| Data | File | Volume | Imported |
|---|---|---|---|
| Lines | `routes.txt` | 202 | Yes |
| Stops (platforms) | `stops.txt`, `location_type=0` | 5,233 | Yes |
| Stations (groups of platforms) | `stops.txt`, `location_type=1` | 2,189 | No |
| Line shapes | `shapes.txt` (19.6 MB) | 514 shapes, 435,442 points, 129 lines | Yes, as one GeoJSON file (E3.8) |
| Stop order of a line | `trips.txt` + `stop_times.txt` (156 MB) | — | No |

- The archive is rebuilt every night (`feed_version 17/10/2026-04:51`, valid until 2027-01-15); the daily import with `ETag` already follows it. Shapes change rarely (service changes, works).
- TBM also publishes NeTEx and SIRI Lite; GTFS stays the source, one adapter for every network (ADR-0001). Bordeaux Métropole's GIS datasets were not checked.
- TBM publishes GTFS-RT TripUpdates (`/gtfsfeed/realtime/bordeaux`, checked 2026-10-18 at night: 16 trips, 564 stop time updates).

## Steps

1. **Stations on the map** from a street zoom level, cached like the line catalogue (E7.1).
2. **One server-rendered page per line** (`/lignes/{line}`) with its stops in order for each direction, from one representative trip per direction (E7.2). Better for RGAA than listing 5,000 stops in the map's text view.
3. **Next departures at a stop:** static stop times corrected by TripUpdates, behind the trip updates port anticipated by ADR-0001; studied in [next-departures.md](next-departures.md), where trip updates alone may be enough.

## Open questions

- Step 3: how to store stop times (1,928,513 rows for TBM on 2026-10-17, plus calendars), and the brief's scope (timetables are out of the MVP).
