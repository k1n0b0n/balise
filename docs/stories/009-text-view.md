# Story 009: Text view equivalent to the map, line catalogue

- **PRD:** E3.5 (and the keyboard criterion of E3.3)
- **Status:** Review
- **Idea:** [ideas/line-catalogue.md](../ideas/line-catalogue.md)

## Acceptance criteria

See E3.5 in [prd.md](../prd.md).

## Tasks

- [x] `GET /api/networks/{network}/lines`: short and long name, mode and its French name, colour; the time of the last catalogue import is the ETag, so a revisit or a refetch gets a `304`
- [x] Positions carry the line id only (`lineId`); `displayId`, `line`, `lineName`, `mode` and `colour` left the payload
- [x] `resources/js/map/lines.ts`: the catalogue, fetched with the first positions; one refetch for lines it lacks; nothing drawn before its first answer, then vehicles of unknown lines (or of a catalogue that failed) shown like vehicles without a line, and a retry every refresh interval
- [x] Positions now start while the basemap loads; a vehicle chosen in the text view before the map is ready opens once it is
- [x] Text view rendered by Livewire (`Home`), then kept current by `resources/js/map/text-view.ts` from Blade `<template>` markup: lines in natural order of their short name, "Ligne inconnue" last, vehicles by number, a button per vehicle that centres the map on it and opens its popup
- [x] Feature tests: line catalogue (fields, `304`, unknown network), slimmer positions, server-rendered text view (order, timezone, empty state); browser test: text view groups, keyboard path to the popup and back, axe

## Notes

- Without JavaScript nothing updates, so the server writes the time of each position ("relevé à 13:59:35", network timezone) rather than an age that would turn false; the module replaces it with the age ("il y a 25 s"), refreshed every 5 s outside any live region.
- The only live region is the status line, worded to the minute ("Mis à jour à 14:00") and written only when its text changes: a screen reader hears it at most once a minute, not on every poll.
- Updates never move the focus: rows and groups are moved only when their place changes, and a moved focused button gets the focus back. The one exception: when the focused vehicle leaves the network, the focus goes to the text view section rather than to the body.
- The popup takes the focus when it opens (MapLibre's `focusAfterOpen`), so keyboard and screen reader users land on it; Escape gives it back to the vehicle's button. The map is centred on the vehicle, at least at zoom 14.
- The buttons' accessible name is "Véhicule 1234, afficher sur la carte": the visible text first (WCAG 2.5.3), the action in a visually hidden span.
- First version drew before the catalogue answered: every marker flashed "?" and the text view regrouped everything under "Ligne inconnue". Drawing now waits for the catalogue's first answer, success or failure.
- Sizes, live TBM at 04:05: line catalogue 2.6 KB compressed for 202 lines; home page 4.4 KB compressed. JavaScript budget: 501.0 KB compressed (+1.4 KB).
- Not covered by an automated test: the catalogue failing or lacking a line, the focus kept when a row moves or its vehicle leaves; checked by reading the code. Keyboard path checked by hand in Chromium as well.
- To do by the maintainer: the VoiceOver check of the PRD criterion.
