# Story 035: The next departures in the station's popup

- **PRD:** E8.2
- **Status:** Done

## Acceptance criteria

See E8.2 in [prd.md](../prd.md), validated with the maintainer on 2026-10-26; the study is [ideas/next-departures.md](../ideas/next-departures.md).

## Tasks

- [x] `trips` table and `CatalogSnapshot::trips()`: each trip's line and `trip_headsign`; the import writes and prunes them, and downloads an unchanged catalogue again while the network has none (the first import after this story's deploy)
- [x] `Departures::board()`: the departures of the next two hours, grouped by line then destination (case ignored), the soonest group first, three times per group; cancelled trips, skipped stops and trips that end at the station left out; the destination from the headsign, else the name of the trip's last stop in the trip update
- [x] `/api/networks/{network}/stations/{station}/departures`: `available`, then each group's line (label, colour), destination and absolute times; 404 for an unknown station; `Cache-Control: public, max-age=30`
- [x] The station popup: the departures under the name, read when it opens and every minute while open, worded at the time they are shown ("dans 3 min", "à 21:41" beyond an hour, "à l'approche"); the list scrolls past 40 % of the screen's height and takes the focus for the keyboard
- [x] Without JavaScript, the search lists each station found, namesakes included, with its departures
- [x] Tests: the import of trips, the grouping and its exclusions, the JSON and its `404`, no real-time data, the search without JavaScript; in the browser, the popup without data, with axe. The tests read no trip updates from TBM (`TBM_TRIP_UPDATES_URL` empty in `phpunit.xml`)
- [x] RGAA self-audit updated: 1.1, 1.2 (the recentre button's decorative crosshair, which E3.4 added), 3.1, 7.3, 9.3, 12.8

## Notes

- **Measured on TBM** (2026-10-26, evening): 68,534 trips, 411 without a headsign; the import takes 5.8 s with them. At Porte de Bourgogne, the popup lists Lianes 16 and Principale 24 one way, trams A, C and E at the platforms of the same name across the street, each station on its own.
- **A bug of E7.1 found here:** a click on a station sent `0` as its id: MapLibre keeps only numeric feature ids. The id now travels in the feature's properties; until now the popup showed only the name, so nothing used it.
- **Two wordings changed from the criteria:** "Aucun départ dans les deux prochaines heures" rather than "dans l'heure", since the list covers two hours so that a time beyond the hour can show; and the answer is kept 30 s by the browser rather than by nginx like the positions, which it reads in a few milliseconds from the cache.
- **The headsign is the operator's text**, sometimes in capitals ("THIERS BENAUGE" beside "Thiers Benauge"): both go in one group, under the spelling met first.
- Trips that end at the station are left out: their last stop time is an arrival.
