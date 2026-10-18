# Idea: line catalogue

- **Status:** Planned → E3.5 (story 009)
- **Related:** ADR-0006 (RGESN), ADR-0013

## Why

Every position update repeats static line data (short and long name, mode, colour) and a vehicle number the browser can derive from the vehicle id. The text view (E3.5) needs the line names of every vehicle anyway.

## Measures (2026-10-18, gzip, 424 vehicles as at midday, 202 lines)

| Positions payload, per update | Size |
|---|---|
| As of E3.3 | 15.3 KB |
| Line names in a catalogue | 12.7 KB (−17 %) |
| All line data in a catalogue, positions with the line id only | 11.8 KB (−23 %) |
| Catalogue, once | 3.5 KB |

Nearly every vehicle moves between two feed polls, so a delta is close to a full payload: the popup fields alone cost about 460 KB per hour of viewing.

## Design

- A line catalogue endpoint, fetched in parallel with the first positions and cached with `ETag`. MapLibre, its style and tiles take longer to load than either request, so markers do not wait for it in practice.
- Positions carry the line id only; the vehicle number shown is derived from the vehicle id in the browser.

## Edge cases

1. **A line the catalogue lacks:** the GTFS import runs daily while a page can stay open for hours. One conditional refetch (`304` if unchanged); a line still unknown shows like a vehicle without a line ("?", neutral colour).
2. **Catalogue failed to load:** vehicles still show, like vehicles without a line, and the catalogue is fetched again on the next tick. The same holds once positions are pushed (E4): the catalogue stays a cached HTTP request.

## Rejected

- **Popup details fetched on click:** about 1 KB per click against 460 KB per hour, but 100 to 300 ms of latency on 4G, a loading state to make accessible (`aria-busy`, announcement, focus), a popup that fails offline, and the text view needs the data for every vehicle anyway.
