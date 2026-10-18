# Story 012: Select a line or a vehicle and let the map follow it

- **PRD:** E3.4
- **Status:** Review

## Acceptance criteria

See E3.4 in [prd.md](../prd.md).

## Tasks

- [x] Line selector in the header: a GET form (`?ligne=` with the line's short name) and a submit button, so it works without JavaScript; the server lists that line first in the text view and keeps the choice selected
- [x] With JavaScript, the form applies without reloading: the line's vehicles are drawn larger and over the others, its group comes first in the text view, the map fits them once, the URL follows (`history.replaceState`)
- [x] Choosing a vehicle (popup or text view) selects it: `?vehicule=`, the map follows it on each update; closing the popup ends the selection; moving the map by hand stops following; a reload opens the vehicle again
- [x] The selected line (or the selected vehicle's line) is drawn over the others, stronger and wider, with a dark casing for 3:1
- [x] Browser tests: selection without reload, URL, reload, following stopped by a drag, axe

## Notes

- **Contrast of the selected line: a dark casing.** Measured against Positron's background (#F2F3F0): 54 of TBM's 202 line colours stay under 3:1 even at full strength (the Lianes' green #76B82A at 2.18:1, the blue #00B1EB at 2.22:1), so no transparency could reach it. The selected line is drawn over the others in its colour mixed at 75 % with the background, still a little transparent, inside a #1F2937 casing (13.9:1), like the markers' outline (E3.2).
- **The other vehicles stay.** The first version showed only the selected line's vehicles; at the maintainer's review, a line is highlighted instead: its markers 1.5 times larger and drawn over the others, its labels at 13 px instead of 11, its group first in the text view. The PRD criterion was reworded accordingly.
- The selected vehicle's line is highlighted too when no line is selected: it is the line the user is looking at.
- `?ligne=` holds the short name, unique among TBM's lines and readable in a shared link; `?vehicule=` the vehicle id without its network prefix, as the popup shows it. A name the network does not know highlights nothing, on the server and in the browser; a vehicle no longer running is dropped from the URL.
- The selector applies on its button, not on change: the same form works without JavaScript, by reloading the page with the server's filtered text view, and choosing in a list never changes the page by itself (RGAA 7.5).
- Following stops on a drag or on the arrow keys, not on a zoom, which keeps the vehicle in the centre. Closing the popup ends the selection.
- The URL is updated with `history.replaceState`: choices do not pile up in the history.
- JavaScript budget: 502.6 KB compressed (+0.8 KB).
