# Idea: the network on the map and in text

- **Status:** Planned → E3.8 (whole-network shapes, ADR-0016), E3.4 (selected line), E7.1, E7.2; Exploring: next departures
- **Related:** ADR-0001, ADR-0002, ADR-0006

## Data (TBM static GTFS of 2026-10-17)

| Data | File | Volume | Imported |
|---|---|---|---|
| Lines | `routes.txt` | 202 | Yes |
| Stops (platforms) | `stops.txt`, `location_type=0` | 5,233 | Yes |
| Stations (groups of platforms) | `stops.txt`, `location_type=1` | 2,189 | No |
| Line shapes | `shapes.txt` (19.6 MB) | 514 shapes, 435,442 points, 129 lines | No |
| Stop order of a line | `trips.txt` + `stop_times.txt` (156 MB) | — | No |

- The archive is rebuilt every night (`feed_version 17/10/2026-04:51`, valid until 2027-01-15); the daily import with `ETag` already follows it. Shapes change rarely (service changes, works).
- TBM also publishes NeTEx and SIRI Lite; GTFS stays the source, one adapter for every network (ADR-0001). Bordeaux Métropole's GIS datasets were not checked.
- TBM publishes GTFS-RT TripUpdates (`/gtfsfeed/realtime/bordeaux`, checked 2026-10-18 at night: 16 trips, 564 stop time updates).

## Weights (gzip, all 514 shapes)

| Simplification | Points | Size |
|---|---|---|
| ~2 m | 94,875 | 472 KB |
| ~6 m | 55,892 | 276 KB |
| ~11 m | 36,718 | 132 KB |
| Stations only | 2,189 | 36 KB |

One variant per line and direction would likely be two to three times smaller; not measured. MapLibre alone is ~430 KB.

## Steps

1. **Stations on the map** from a street zoom level, cached like the line catalogue (E7.1).
2. **The selected line's shape** under its vehicles, highlighted in the whole-network file (E3.4, after E3.8).
3. **One server-rendered page per line** (`/lignes/{line}`) with its stops in order for each direction, from one representative trip per direction (E7.2). Better for RGAA than listing 5,000 stops in the map's text view.
4. **Whole-network shapes:** decided 2026-10-18, a static GeoJSON file generated at import only when shapes change, every line faint and the selected one less transparent (E3.8, ADR-0016); vector tiles when a second network needs them.
5. **Next departures at a stop:** static stop times corrected by TripUpdates, behind the trip updates port anticipated by ADR-0001.

## Open questions

- Simplification tolerance: chosen by user-experience tests at street and city zoom levels.
- Step 4: GeoJSON or tiles.
- Step 5: how to store stop times (1,928,513 rows for TBM on 2026-10-17, plus calendars), and the brief's scope (timetables are out of the MVP).
