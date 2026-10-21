# Story 019: The line selector: running lines, by mode

- **PRD:** E3.4
- **Status:** In progress

## Acceptance criteria

See E3.4's last two criteria in [prd.md](../prd.md).

## Tasks

- [x] The server lists in the selector the lines that have a position, and the selected line whatever its vehicles
- [x] The map module rebuilds the selector's options when the lines that run change, by mode then name as the server does, and waits for the selector to lose the focus
- [x] Feature and browser tests; checked with the keyboard and VoiceOver
- [ ] A mode selector before the line selector, filtering it in the browser and on the server without JavaScript; checked with VoiceOver
- [ ] Closing: story notes, *Current state*, ideas review

## Notes

- **When the list changes** (asked by the maintainer: at load and while the page stays open, not at load only): the page is meant to stay open for hours, from a nearly empty network at night to the morning, so a list fixed at load would soon be wrong. Changing the options of a focused `<select>` would move what a keyboard or screen reader user is going through, so a change waits for the focus to leave.
- **The browser keeps the list from the positions it already has**, through the line catalogue it already fetches (mode, names): no new request. It rebuilds the options only when the set of running lines changes, keeps the chosen value, and orders them as the server does (the enum's modes, then natural order of the short name).
- **A focused selector is left alone:** a change waits for `blur`. Choosing a line with the keyboard keeps the focus on the selector, and the "deselect" link gives it back to the selector, so in both cases the list changes once the user moves on.
- **Testing a line that stops** needs the vehicle to age out through `RecordPositions`: deleting its row directly bypasses the change log, and the browser, which receives only changes, never hears of it.
- **VoiceOver and a line's names** (checked by the maintainer, VoiceOver on macOS): with an en dash between the short and the long name it said "tiret cadratin"; with any dash, en dash or hyphen, it spelled out the words after it in the selector's options ("T, R, A, M", "L, I, A, N, E, S"), for "Tram A" as for a long name. Tried on a page of six labels: the long name alone, the short name alone, or the long name in parentheses read normally; a final full stop changed nothing. The maintainer then chose the long name alone, "Tram A" rather than "A (Tram A)", in the selector, the text view's headings and the popup (`Line::label()` on the server, `lineLabel` in the browser), and the mode with the short name for a line without one. At TBM, 22 of 202 long names do not contain the short name, and two pairs read the same (160 and 760 are both "Bus Relais B"); shown as they are, by the maintainer's choice. The vehicles' names keep their hyphen (ADR-0018), which VoiceOver reads well on buttons.
- **Filtering a long list** (asked by the maintainer, 60 to 100 lines run by day): the options weighed were the native type-ahead of a `<select>` (already works, unknown to most), a `<datalist>` (read poorly by VoiceOver in Firefox and Safari, no groups), tram shortcuts, a filter field beside the selector, and a combobox (GOV.UK's `accessible-autocomplete`, a new dependency, or one written by hand after the WAI-ARIA pattern). The maintainer chose a mode selector before the line selector: two native selects, nothing new for assistive technologies. Choosing a mode narrows the lines at once and changes neither the selection nor the URL; without JavaScript the form's button reloads with `?mode=`. The modes listed are those that run, and the chosen one stays listed while none of its lines runs, as the selected line does.
