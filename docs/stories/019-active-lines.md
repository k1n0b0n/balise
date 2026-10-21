# Story 019: Only the lines that run in the selector

- **PRD:** E3.4
- **Status:** In progress

## Acceptance criteria

See E3.4's last criterion in [prd.md](../prd.md).

## Tasks

- [x] The server lists in the selector the lines that have a position, and the selected line whatever its vehicles
- [x] The map module rebuilds the selector's options when the lines that run change, by mode then name as the server does, and waits for the selector to lose the focus
- [ ] Feature and browser tests; checked with the keyboard and VoiceOver
- [ ] Closing: story notes, *Current state*, ideas review

## Notes

- **When the list changes** (asked by the maintainer: at load and while the page stays open, not at load only): the page is meant to stay open for hours, from a nearly empty network at night to the morning, so a list fixed at load would soon be wrong. Changing the options of a focused `<select>` would move what a keyboard or screen reader user is going through, so a change waits for the focus to leave.
- **The browser keeps the list from the positions it already has**, through the line catalogue it already fetches (mode, names): no new request. It rebuilds the options only when the set of running lines changes, keeps the chosen value, and orders them as the server does (the enum's modes, then natural order of the short name).
- **A focused selector is left alone:** a change waits for `blur`. Choosing a line with the keyboard keeps the focus on the selector, and the "deselect" link gives it back to the selector, so in both cases the list changes once the user moves on.
- **Testing a line that stops** needs the vehicle to age out through `RecordPositions`: deleting its row directly bypasses the change log, and the browser, which receives only changes, never hears of it.
