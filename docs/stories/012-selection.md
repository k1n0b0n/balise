# Story 012: Select a line or a vehicle and let the map follow it

- **PRD:** E3.4
- **Status:** Review

## Acceptance criteria

See E3.4 in [prd.md](../prd.md).

## Tasks

- [x] Line selector in the header: a GET form (`?ligne=` with the line's short name) and a submit button, so it works without JavaScript; the server lists that line first in the text view and keeps the choice selected
- [x] With JavaScript, the form applies without reloading: the line's vehicles are drawn larger and over the others, its group comes first in the text view, the map fits them once, the URL follows (`history.replaceState`)
- [x] Choosing a vehicle (popup or text view) selects it and its line: `?ligne=` and `?vehicule=`, the map follows it on each update; closing the popup ends the selection; moving the map by hand stops following; a reload opens the vehicle again
- [x] The selected line is drawn over the others at the same width, in its full colour, darkened where needed to reach 3:1
- [x] Stacked markers: each click on them opens the next vehicle, and the popup says how many there are
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
- **Stacked markers** (maintainer's review): one of two vehicles at the same place could not be opened from the map. Each click on the spot now opens the next vehicle, in order of id, and the popup says "Véhicule 1 sur 2 à cet endroit : cliquez à nouveau pour le suivant." Chosen over a list of the stacked vehicles in a popup, and over spreading the markers, which would show false positions; the text view still reaches every vehicle.
