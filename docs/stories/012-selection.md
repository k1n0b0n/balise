# Story 012: Select a line or a vehicle and let the map follow it

- **PRD:** E3.4
- **Status:** Done

## Acceptance criteria

See E3.4 in [prd.md](../prd.md).

## Tasks

- [x] Line selector in the header: a GET form (`?ligne=` with the line's short name) and a submit button, so it works without JavaScript; the server lists that line first in the text view and keeps the choice selected
- [x] With JavaScript, the form applies without reloading: the line's vehicles are drawn larger and over the others, its group comes first in the text view, the map fits them once, the URL follows (`history.replaceState`)
- [x] Choosing a vehicle (popup or text view) selects it and its line: `?ligne=` and `?vehicule=`, the map follows it on each update; closing the popup ends the selection; moving the map by hand stops following; a reload opens the vehicle again
- [x] The selected line is drawn over the others at the same width, in its full colour, darkened where needed to reach 3:1
- [x] Stacked markers: the popup opens on the first, and its arrows go through the others
- [x] Browser tests: selection without reload, URL, reload, following stopped by a drag, axe

## Notes

- **Contrast of the selected line: its colour, darkened where needed.** Measured against Positron's background (#F2F3F0): 54 of TBM's 202 lines (5 of its 24 colours) stay under 3:1 even at full strength, such as the Lianes' green #76B82A (2.18:1) and blue #00B1EB (2.22:1). The selected line is drawn in its full colour, mixed with the markers' dark grey #1F2937 in steps of 5 % until it reaches 3:1 (30 % at most on TBM), so every selected line reaches 3:1 and keeps its hue. A first version drew it wider inside a dark casing; at the maintainer's review it keeps the others' width, so as not to hide more of the map.
- **The other vehicles stay.** The first version showed only the selected line's vehicles; at the maintainer's review, a line is highlighted instead: its markers 1.5 times larger and drawn over the others, its labels at 13 px instead of 11, its group first in the text view. The PRD criterion was reworded accordingly.
- **Choosing a vehicle selects its line** (selector, URL, highlight), as asked at the maintainer's review: choosing another vehicle moves the highlight to its line. Closing the popup ends the vehicle's selection and keeps the line.
- **A white ring sets the selected line's vehicles apart from its shape**, drawn in the same colour under them (maintainer's review): 2 px outside the dark outline, which stays next to the marker's colour for its 3:1.
- `?ligne=` holds the short name, unique among TBM's lines and readable in a shared link; `?vehicule=` the vehicle id without its network prefix, as the popup shows it. A name the network does not know highlights nothing, on the server and in the browser; a vehicle no longer running is dropped from the URL.
- The selector applies on its button, not on change: the same form works without JavaScript, by reloading the page with the server's filtered text view, and choosing in a list never changes the page by itself (RGAA 7.5).
- Following stops on a drag or on the arrow keys, not on a zoom, which keeps the vehicle in the centre. Closing the popup ends the selection.
- The URL is updated with `history.replaceState`: choices do not pile up in the history.
- JavaScript budget: 502.6 KB compressed (+0.8 KB).
- **Stacked markers** (maintainer's review): one of two vehicles at the same place could not be opened from the map. A click opens the popup on the first (in order of id), with arrows ‹ › and "1 sur 2 à cet endroit" to go through the others; the arrows are buttons, named for screen readers, and keep the focus. A first version went to the next vehicle on each click, which nobody would guess. Spreading the markers was rejected: it shows false positions. The text view still reaches every vehicle.
- CodeRabbit reviewed the pull request on its own when it was marked ready: three findings, fixed. An unknown `?ligne=` fitted the map to the vehicles without a line; the recentring on a followed vehicle ran on every redraw and cancelled the zoom to a vehicle opened from the text view or the URL, and the fit to a line (it now runs on new positions only, for a vehicle followed before them); choosing another line kept a vehicle of the old line selected and followed (its popup now closes).
- **Deselecting** (after the merge, maintainer's request): the selector's "Toutes les lignes" was the only way back. Closing the popup now ends the selection of the vehicle and of its line, and a link in the text view, "Désélectionner la ligne A", shown only with a selected line, deselects it; without JavaScript it loads the page without `?ligne=`. The focus then goes to the text view, since the link hides.
