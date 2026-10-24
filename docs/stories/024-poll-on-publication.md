# Story 024: Read the feed as soon as it is published

- **PRD:** E2.5
- **Status:** Done

## Acceptance criteria

See E2.5 in [prd.md](../prd.md).

## Tasks

- [x] Measure when TBM publishes, from the recorded payloads
- [x] `VehicleFeedSnapshot` carries the time the provider gave the feed (`publishedAt`, from the GTFS-RT header); the poll remembers it and waits until a second after the next publication before reading
- [x] One more request, 2 s later, when the feed has not changed
- [x] `seconds_after_publication` in the poll's log line; tests on the waiting, the retry and what is remembered (`Sleep::fake()`)
- [x] Measured locally; on the server after the next deploy (*Current state*)

## Notes

- **When TBM publishes.** 360 payloads recorded on 2026-10-21 between 11:00 and 13:00: the header's time steps by 20 s (353 times; 19 or 21 s six times) and always falls on :02, :22 or :42 of the minute (357 times; :03 three times). The recorder, which ran at its own pace, read each payload 1 to 15 s after that time: the feed is served about a second after it is generated.
- **Before: 18 s late on every poll.** The scheduler's sub-minute runs start at :00, :20 and :40, two seconds before each publication: each run read the feed published 18 s earlier, the worst possible phase.
- **After: 1.2 s, five polls out of five**, measured locally with the scheduler stopped and the command run by hand 10 s apart. A run now lasts about 3 s, mostly waiting. Polls stay at three a minute; one that finds the feed unchanged asks once more, so requests rise only when the provider is late.
- **The phase is learnt, not configured.** The poll keeps the time of the last feed and waits for the next multiple of the interval after it, plus one second. If the last publication was served less than half an interval ago, it reads at once rather than wait for the next one. A provider that moves its second is followed from the next feed.
- **Assumptions, written in the command:** the provider publishes at the poll's interval (otherwise the wait gains nothing, and harms nothing); both clocks are synchronised by NTP (a server a few seconds ahead asks before the publication, gets a `304` and retries, which the log line's `retried` shows; more than 3 s ahead, it reads the previous feed); one network per scheduler, which runs polls one after another, so several networks' waits would add up. The longest run grows from about 13 s (a fetch timing out) to about 42 s (a wait, two fetches timing out, the retry's 2 s), still alone in its slot with one network.
- **A failed second request is not a failure**: the first answer said the feed had not changed, so the run carries on as for a `304` and still ages stale vehicles out; it is logged as information.
- **Review by an agent** (before CodeRabbit): the dataset built `Sleep` objects before `Sleep::fake()`, which really slept 11 s when those cases were filtered out; the expected waits are now numbers. It also asked for the retry's own error handling, the cases of a publication not served yet and of exactly half an interval, `?int` instead of `mixed`, and the assumptions above.
- **Polling every 5 s was weighed and dropped**: a delay of 1 to 5 s, four times the requests to TBM and four times the PHP processes (about 0.45 s of CPU each on the server), against 1.2 s at today's cost.
- **Scheduled polls' logs are lost.** The scheduler runs each command with its output sent to `/dev/null`, and the application logs to `stderr` (local and production), so the log lines of scheduled polls, failures included, reached no one; the command run by hand showed them. Fixed by story 025 (#80), so the server's measurement is read in `docker compose logs scheduler`.
