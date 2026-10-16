# Story 007: Vehicles as points coloured by line, with clustering

- **PRD:** E3.2
- **Status:** Review

## Acceptance criteria

See E3.2 in [prd.md](../prd.md).

## Tasks

- [x] `GET /api/networks/{network}/vehicles?since={version}`: every vehicle, or only those changed and removed since the version; the version is the ETag, and an up-to-date browser gets a `304` without a body
- [x] `App\Support\PositionChangeLog`, a listener of `PositionsUpdated` (ADR-0002): one version per event, the last 15 kept in the cache (about 5 min); a browser further behind, or after a cache clear, gets everything again
- [x] Label colours: black or white, whichever contrasts more (`App\Support\Contrast`, WCAG formula); lines without a colour and vehicles without a known line in neutral grey `#4B5563`
- [x] Map module: live store `resources/js/map/live.ts` (every 5 s, `map.refresh_every_seconds`, paused while the tab is hidden), layers and group buttons in `resources/js/map/vehicles.ts`
- [x] Feature tests for the endpoint and the contrast rule, browser test for the groups (accessible name, keyboard, axe)

## Notes

- The API routes run without the `web` middleware: no session row and no cookie per poll, so the answers can be cached later (E5.3).
- Point outline `#1F2937` reaches 13.9:1 against Positron's background (`#F2F3F0`), so every point passes 3:1 whatever its line colour; the published text colours of the lines are ignored.
- Groups (clusters) are HTML buttons over the map, not canvas shapes: "3 véhicules, zoomer sur le groupe" is read by screen readers, and Enter zooms in. Group ids change with every data update; a focused button moves to the closest new group instead of disappearing.
- On the map, a vehicle without a known line shows "?" (a point cannot hold "Ligne inconnue"); the full wording comes with the popup (E3.3) and the text view (E3.5).
- The point grows with the label: TBM short names go up to 8 characters ("Artigues", "TBNight").
- Live TBM at 23:12 on 2026-10-16: 124 vehicles, first answer 3.7 KB compressed, each update about 3.6 KB. The delta saves little, since almost every running vehicle moves between two polls (story 005); it pays at standstills and with `304`.
- The page asks every 5 s, not every 20 s like the feed poll: a change shows after 2.5 s on average instead of 10 s, and about three answers in four are a `304` that only reads the cache. Four times more requests, to be weighed in the load test (E5.3); E4 replaces this polling with push.
- JavaScript budget: 500.7 KB compressed (+4.9 KB).
- Not covered by an automated test: the pause while the tab is hidden (`visibilitychange`); checked by reading the code.
