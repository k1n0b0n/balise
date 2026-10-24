# Story 027: Hardening from the code review

- **PRD:** E1.7
- **Status:** Done

## Acceptance criteria

See E1.7 in [prd.md](../prd.md). The review, by an agent over the whole of `main` on 2026-10-24, also found the shapes lost in production (story 026) and points for the server (*Current state*).

## Tasks

- [x] `PollNetwork`: a failed fetch still calls the write path with no positions, so vehicles age out while the feed is down
- [x] `GtfsRealtimeFeed`: (0, 0) dropped with the impossible coordinates
- [x] `routes/console.php`: the import in the background
- [x] Controllers: the network checked against the keys of the configuration
- [x] `popup.ts`: Escape from inside the popup or the map only
- [x] `config/app.php`: `fr` by default; `Line.php`: the colour's docblock back on `colour()`
- [x] `ArchitectureTest`: single write path and map module boundary, each failing on a deliberate violation, then removed
- [x] AGENTS.md: the polling store, the change log as the event's listener

## Notes

- **A feed down froze the map.** A fetch that threw returned before `RecordPositions`, so nothing aged out: an hour of outage, or an empty body at night, left the last vehicles on the map and in the text view, "il y a 47 min", against E2.3. The failure path now ages them out before reporting the failure; tested with a vehicle 6 minutes old and a 503.
- **(0, 0) was kept on purpose by a test** that called it a possible coordinate. It is, but it is also what a position without its coordinates decodes to, and what feeds send when the GPS is lost: the vehicle showed in the Gulf of Guinea and fitting its line zoomed out to the world. Only the exact pair is dropped; a latitude of 0 alone is kept.
- **The morning import held up the polls.** `schedule:run` runs its commands one after another; the import, defined first, kept the 05:30 polls waiting for one to two minutes. In the background, it runs in its own process; `withoutOverlapping` and the log destination (story 025) still apply.
- **`Config::has("networks.{$network}")` accepted `tbm.vehicles`**, so `/api/networks/tbm.vehicles/vehicles` answered 200 with nothing; the commands already used the keys.
- **Escape anywhere closed the popup**: in portrait, Escape in the side panel closed the panel and, unseen, the popup and the selection.
- **The architecture tests read the source** rather than use Pest's `arch()`, which sees which classes are used, not whether a model is read or written. The write-path test looks for a write method (`upsert`, `update`, `delete`, `save`, `create`…) in any file of `app/` that names `VehiclePosition`, except `RecordPositions`.
- **Not done here: the change log in the write path's transaction** (review point 10). It holds because the cache is in PostgreSQL today, but the server story moves the cache to Redis, where no transaction spans both; see *Current state*.
