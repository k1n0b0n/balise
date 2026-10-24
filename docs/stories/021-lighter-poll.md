# Story 021: A lighter poll

- **PRD:** E2.4
- **Status:** Done

## Acceptance criteria

See E2.4 in [prd.md](../prd.md); the server's timings that called for it are in [story 018](018-live.md#notes).

## Tasks

- [x] `docker/php/production.ini`: OPcache for the CLI, with its file cache in `/tmp/opcache`, in the production image only
- [x] `RecordPositions`: the stored state as plain rows of the compared columns; the event's positions hydrated from the written rows
- [x] Test: one read and one write per poll, for 1 and for 50 vehicles; it fails on the previous code, which read the changed positions again
- [x] Timed locally before and after; on the server after the next deploy (*Current state*)

## Notes

- **Where a poll's time goes, locally.** One `RecordPositions` call in a new process, on two TBM feeds recorded 20 s apart on 2026-10-21 at noon (394 positions read, 29 dropped, 365 changed, 2 removed), the first one already stored: about 50 ms, of which 12 ms of SQL (the read 2 ms, the upsert 10 ms) and 7 ms for the change log the event feeds. Most of the rest is PHP compiling Eloquent and the query builder on first use, which every new process does again.
- **The lighter `RecordPositions`: 61 to 67 ms before, 51 to 56 ms after** (five new processes each); in a warm process, 28 ms either way. Fewer models are built, but it is not where the time was: compiling the code is.
- **OPcache for the CLI: `artisan --version` in the production image from 0.09 to 0.10 s down to 0.04 to 0.05 s**, once the first run (0.24 s) has filled the cache: 20 MB in the container's `/tmp`, so each container has its own and a deploy, which creates new containers, starts from an empty one. On the server, story 018 measured 0.71 s down to about 0.35 s for the same command.
- **Seconds against microseconds** (found by CodeRabbit): the database keeps `recorded_at` to the second, the age limit has the microseconds of the reception. Comparing both in whole seconds kept a vehicle at the limit one poll longer than before; removal compares full dates again, under a test.
- **Timestamps are still checked.** The code of a release never changes, but the entry point writes Laravel's caches (`bootstrap/cache`) again at each start, and a restart keeps the container's files: without the check, a restart after a change to `app.env` would run the configuration of the previous start.
- **Local development is unchanged**: the file is copied into the production stage only, so the dev and test images keep compiling every run, as code changes there.
