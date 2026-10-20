# Story 009: Text view equivalent to the map, line catalogue

- **PRD:** E3.5 (and the keyboard criterion of E3.3)
- **Status:** Done
- **Idea:** line catalogue, its study in the notes below

## Acceptance criteria

See E3.5 in [prd.md](../prd.md).

## Tasks

- [x] `GET /api/networks/{network}/lines`: short and long name, mode and its French name, colour; the time of the last catalogue import is the ETag, so a revisit or a refetch gets a `304`
- [x] Positions carry the line id only (`lineId`); `displayId`, `line`, `lineName`, `mode` and `colour` left the payload
- [x] `resources/js/map/lines.ts`: the catalogue, fetched with the first positions; one refetch for lines it lacks, again at most once an hour for a line still missing; nothing drawn before its first answer, then vehicles of unknown lines (or of a catalogue that failed) shown like vehicles without a line, and a retry every refresh interval
- [x] Positions now start while the basemap loads; a vehicle chosen in the text view before the map is ready opens once it is
- [x] Text view rendered by Livewire (`Home`), then kept current by `resources/js/map/text-view.ts` from Blade `<template>` markup: lines in natural order of their short name, "Ligne inconnue" last, vehicles by number, a button per vehicle that centres the map on it and opens its popup
- [x] Layout: in landscape the text view is a column beside the map with its own scroll; in portrait, once Alpine runs, a side panel over the map opened and closed by a button in the header (`aria-expanded`), a close button and Escape; the skip link opens it. Choosing a vehicle closes it; the popup announces with `vehicle-popup-focus-lost` that its return target is hidden, and the page reopens the panel on it
- [x] Feature tests: line catalogue (fields, `304`, unknown network), slimmer positions, server-rendered text view (order, timezone, empty state); browser test: text view groups, keyboard path to the popup and back, axe

## Notes

- Without JavaScript nothing updates, so the server writes the time of each position ("relevé à 13:59:35", network timezone) rather than an age that would turn false; the module replaces it with the age ("il y a 25 s"), refreshed every 5 s outside any live region.
- The only live region is the status line, worded to the minute ("Mis à jour à 14:00") and written only when its text changes: a screen reader hears it at most once a minute, not on every poll.
- Updates never move the focus: rows and groups are moved only when their place changes, and a moved focused button gets the focus back. The one exception: when the focused vehicle leaves the network, the focus goes to the text view section rather than to the body.
- The popup takes the focus when it opens (MapLibre's `focusAfterOpen`), so keyboard and screen reader users land on it; Escape gives it back to the vehicle's button. The map is centred on the vehicle, at least at zoom 14.
- The buttons' accessible name is "Véhicule 1234, Tram A, afficher sur la carte": the visible text first (WCAG 2.5.3), then, in a visually hidden span, the line (VoiceOver check: with Tab the headings are skipped, so a name without the line left the vehicle out of context) and the action.
- First version drew before the catalogue answered: every marker flashed "?" and the text view regrouped everything under "Ligne inconnue". Drawing now waits for the catalogue's first answer, success or failure.
- Sizes, live TBM at 04:05: line catalogue 2.6 KB compressed for 202 lines; home page 4.4 KB compressed. JavaScript budget: 501.0 KB compressed (+1.4 KB).
- Not covered by an automated test: the catalogue failing or lacking a line, the focus kept when a row moves or its vehicle leaves; checked by reading the code. Keyboard path checked by hand in Chromium as well.
- The panel is a disclosure, not a modal dialog: the map behind stays reachable. Without JavaScript the text view stays under the map in portrait. Closing the popup with the mouse also reopens the panel, since the focus was in the popup.
- The visually hidden texts are absolutely positioned: the scrolling column must be positioned too (`relative`), or they escape it and lengthen the page.
- Live TBM at 04:11 on 2026-10-18: one vehicle, a tram whose trip `nt_268436716` has no `route_id` and is not in the static GTFS (probably an unplanned run). ADR-0013 measured `route_id` on 100 % of vehicles at midday; at night TBM also sends such trips. Shown as "Ligne inconnue", as ADR-0013 requires; investigation in [ideas/vehicles-without-line.md](../ideas/vehicles-without-line.md).
- To do by the maintainer: the VoiceOver check of the PRD criterion.
- VoiceOver with Firefox and Chrome, landscape and portrait, 2026-10-18 (maintainer): skip link, headings, popup opened with Enter and closed with Escape, status line at most once a minute, side panel opened, closed and reopened on the chosen vehicle. One finding: with Tab the line headings are skipped, so "Véhicule 1234, afficher sur la carte" said no line; fixed in #39, the button's name now carries it. The browser test checks the name computed for assistive technologies.
- CodeRabbit reviewed #37 (one finding: a line still missing after a refetch was never looked for again; now at most once an hour) and #39 (one nitpick: assert the computed accessible name rather than the text content).

### Study

The line catalogue idea, studied before the story (moved from `ideas/line-catalogue.md`).

#### Why

Every position update repeats static line data (short and long name, mode, colour) and a vehicle number the browser can derive from the vehicle id. The text view (E3.5) needs the line names of every vehicle anyway.

#### Measures (2026-10-18, gzip, 424 vehicles as at midday, 202 lines)

| Positions payload, per update | Size |
|---|---|
| As of E3.3 | 15.3 KB |
| Line names in a catalogue | 12.7 KB (−17 %) |
| All line data in a catalogue, positions with the line id only | 11.8 KB (−23 %) |
| Catalogue, once | 3.5 KB |

Nearly every vehicle moves between two feed polls, so a delta is close to a full payload: the popup fields alone cost about 460 KB per hour of viewing.

#### Design

- A line catalogue endpoint, fetched in parallel with the first positions and cached with `ETag`. MapLibre, its style and tiles take longer to load than either request, so markers do not wait for it in practice.
- Positions carry the line id only; the vehicle number shown is derived from the vehicle id in the browser.

#### Edge cases

1. **A line the catalogue lacks:** the GTFS import runs daily while a page can stay open for hours. One conditional refetch (`304` if unchanged); a line still unknown shows like a vehicle without a line ("?", neutral colour).
2. **Catalogue failed to load:** vehicles still show, like vehicles without a line, and the catalogue is fetched again on the next tick. The same holds once positions are pushed (E4): the catalogue stays a cached HTTP request.

#### Rejected

- **Popup details fetched on click:** about 1 KB per click against 460 KB per hour, but 100 to 300 ms of latency on 4G, a loading state to make accessible (`aria-busy`, announcement, focus), a popup that fails offline, and the text view needs the data for every vehicle anyway.
