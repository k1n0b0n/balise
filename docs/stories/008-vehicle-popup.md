# Story 008: Vehicle popup

- **PRD:** E3.3
- **Status:** Review

## Acceptance criteria

See E3.3 in [prd.md](../prd.md).

## Tasks

- [x] Endpoint: `displayId` (vehicle id without the network prefix), `lineName` (long name) and `recordedAt` for each vehicle
- [x] `resources/js/map/popup.ts`: one popup at a time, opened by a click on a marker (6 px tolerance), closed by Escape, its close button or a click elsewhere on the map; it follows its vehicle on each update and closes when the vehicle disappears
- [x] Age of the position rewritten every second ("il y a 25 s", "il y a 3 min"), outside any live region
- [x] Focus given back on close to the element that had it when the popup opened
- [x] Feature test for the new fields, browser test: click, content, axe, Escape, focus back on the canvas

## Notes

- The PRD criterion first asked for "pressing Enter on a vehicle". ADR-0011 had already decided that canvas markers cannot take focus and that the text view is the keyboard path. RGAA 4.1.2 test 7.3.1, checked on accessibilite.numerique.gouv.fr, accepts "un élément accessible par le clavier et tout dispositif de pointage permettant de réaliser la même action […] présent dans la page". The criterion now sends keyboard users to the text view, and E3.5 gained a criterion: each vehicle has a button that opens its popup.
- Feature ids must be numbers in MapLibre: string ids were silently dropped since E3.2. The vehicle id now travels as a property.
- The age is formatted by hand: `Intl.RelativeTimeFormat('fr', { style: 'narrow' })` gave "-25 s" in Node's ICU, and browsers may differ too.
- MapLibre's close button is 14 px wide; `resources/css/app.css` makes it 24 px (WCAG 2.5.8), with a selector one class more specific, since the MapLibre stylesheet loads after `app.css`.
- The browser test was checked against a broken focus return: it fails.
- Not covered by an automated test: the popup following its vehicle and closing when the vehicle disappears (`VehiclePopup::update`); checked by reading the code. The keyboard criterion stays open until E3.5.
- Payload: 4.1 KB compressed for 100 vehicles with the new fields. JavaScript budget: 499.6 KB compressed (+2.7 KB).
