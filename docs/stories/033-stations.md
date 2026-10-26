# Story 033: Stations on the map, found by name

- **PRD:** E7.1
- **Status:** Done

## Acceptance criteria

See E7.1 in [prd.md](../prd.md); the data is described in [ideas/network-on-map.md](../ideas/network-on-map.md).

## Tasks

- [x] `stations` table and `stops.station_id`; `CatalogSnapshot::stations()`: the GTFS stations (`location_type` 1) and the platforms without a `parent_station`; each platform's station
- [x] The import writes and prunes the stations, and downloads an unchanged catalogue again while the network has none (the first import after this story's deploy)
- [x] `/api/networks/{network}/stations`: GeoJSON, coordinates to five decimals, the import's time as ETag, like the line catalogue
- [x] The map module: the stations fetched once the map reaches zoom 15 or the search is used, drawn under the vehicles as white rings with a dark outline; their name on hover, in a popup on click (a vehicle over a station keeps the click)
- [x] The station search in the header: names suggested (`datalist`), accents and case ignored, the exact name first; the map centred on the station with its popup open, and a status that says so, the focus left in the field; a search sent before the map is ready waits for it; without JavaScript, the page lists the matching names
- [x] Tests: the import (station, lone platform, entrance left out), the download while there are no stations, the endpoint and its `304`, the search without JavaScript; in the browser, the search found and not found, with axe
- [x] The map page's JavaScript: 515 KB, under its 600 KB budget
- [x] The RGAA self-audit updated for the search: label, button, status message, search landmark, tab order (7.3, 7.5, 11.1, 11.2, 11.9, 11.10, 12.6, 12.8)
- [ ] The search heard with VoiceOver in Firefox and Chrome, as for story 009: the maintainer's check, during the review

## Notes

- **3,487 stations for TBM**: its 2,189 stations and 1,298 platforms that belong to none; all 5,235 platforms point to an existing station. The file is 476 KB, 62 KB gzipped, fetched only at street level or by the search.
- **The search is the keyboard's way in.** MapLibre's markers cannot take the focus; vehicles are reached from the text view (E3.5), but 3,487 stations would make it unreadable. The search finds one by its name and opens it; the popup does not take the focus, so a screen reader hears the status ("Carte centrée sur l'arrêt Quinconces.") rather than a close button.
- **A search sent too early**, before the map had loaded, first answered "not ready"; it now waits for the map, which is what a quick typist expects.
- **After the review (2026-10-26, maintainer):** a suggestion chosen in the list shows its station at once, without the button (a name typed letter by letter still waits for it); "Carte centrée sur l'arrêt …" is said to screen readers only, the map and the popup showing it on screen.
- **Fixed after the review (2026-10-26):** a click on a station while another one's popup was open only closed it: MapLibre's `closeOnClick` closed the popup the click had just moved. The station popup now closes on a click away from any station.
