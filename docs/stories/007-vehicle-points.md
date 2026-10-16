# Story 007: Vehicles as markers coloured by line and shaped by mode

- **PRD:** E3.2
- **Status:** Review

## Acceptance criteria

See E3.2 in [prd.md](../prd.md).

## Tasks

- [x] `GET /api/networks/{network}/vehicles?since={version}`: every vehicle, or only those changed and removed since the version; the version is the ETag, and an up-to-date browser gets a `304` without a body
- [x] `App\Support\PositionChangeLog`, a listener of `PositionsUpdated` (ADR-0002): one version per event, the last 15 kept in the cache (about 5 min); a browser further behind, or after a cache clear, gets everything again
- [x] Marker shape by mode, from the line's `mode` sent by the endpoint
- [x] Lines without a colour and vehicles without a known line in neutral grey `#4B5563`
- [x] Map module: live store `resources/js/map/live.ts` (every 5 s, `map.refresh_every_seconds`, paused while the tab is hidden), layers in `resources/js/map/vehicles.ts`
- [x] Feature tests for the endpoint, browser test of the drawn vehicles (axe)

## Notes

- The API routes run without the `web` middleware: no session row and no cookie per poll, so the answers can be cached later (E5.3).
- First version: points sized to hold their label (black or white text by WCAG ratio) and clusters as HTML buttons. Changed during review, at the maintainer's request: no clustering, small markers, the short name written beside the marker, then one shape per mode (RGAA 3.1: colour is not the only cue). The clustering criterion was dropped from the PRD and the contrast criterion now targets the basemap.
- Points and labels use one dark grey, `#1F2937`: 13.9:1 against Positron's background (`#F2F3F0`), so outlines pass 3:1 and labels 4.5:1 whatever the line colour; a white halo keeps labels readable over roads. The published text colours of the lines are not used.
- Markers are drawn on a canvas the first time the map needs a shape and colour pair (`setMissingStyleImageResolver`; MapLibre 6 no longer resolves images from the `styleimagemissing` event), at twice the pixel density: no image file, 12 images for TBM at 23:00. Sizes: circle 7 px, square 6.5 px (it looks larger), diamond and triangle 8 px, plus a 1 px outline.
- MapLibre hides labels that would overlap, never the markers: at low zoom many labels are missing, they appear as you zoom in.
- A vehicle without a known line is labelled "?" on the map; "Ligne inconnue" comes with the popup (E3.3) and the text view (E3.5).
- Live TBM at 23:12 on 2026-10-16: 124 vehicles, first answer 3.7 KB compressed, each update about 3.6 KB. The delta saves little, since almost every running vehicle moves between two polls (story 005); it pays at standstills and with `304`.
- The page asks every 5 s, not every 20 s like the feed poll: a change shows after 2.5 s on average instead of 10 s, and about three answers in four are a `304` that only reads the cache. Four times more requests, to be weighed in the load test (E5.3); E4 replaces this polling with push.
- JavaScript budget: 496.9 KB compressed (+1.1 KB).
- Not covered by an automated test: the pause while the tab is hidden (`visibilitychange`); checked by reading the code.
