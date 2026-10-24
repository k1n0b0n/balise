# Story 025: Scheduled commands' logs reach the container's logs

- **PRD:** E1.6
- **Status:** Done

## Acceptance criteria

See E1.6 in [prd.md](../prd.md).

## Tasks

- [x] `routes/console.php`: the poll and the import append their output to `/proc/1/fd/2`, the `stderr` of the scheduler (`schedule:work`, the container's first process)
- [x] Test: both scheduled commands append their output there
- [x] Checked locally: `docker compose logs scheduler` shows "Vehicle feed of tbm recorded." with its counts

## Notes

- **Why nothing was logged.** The scheduler starts each command in its own process with `> /dev/null 2>&1`, and containers log to `stderr` (`LOG_CHANNEL=stderr` in `compose.yaml` and on the server): the lines of every scheduled poll and import, failures included, were thrown away since E2.2. Running a command by hand showed them, which hid the problem. Found while measuring E2.5.
- **`/proc/1/fd/2`** is the `stderr` of the container's first process, which Docker collects: `schedule:work` itself, both locally (`command` of `compose.yaml`) and on the server, where the entry point hands over to it with `exec`. The commands run as the same user, so they may write to it.
- **Rejected:** a log file under `storage/logs`, which nobody reads in a container and which grows without rotation; running the commands in the background (`runInBackground`), which changes how overlapping is prevented.
