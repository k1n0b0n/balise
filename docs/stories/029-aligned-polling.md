# Story 029: Ask for the positions when the next version is expected

- **PRD:** E4.1
- **Status:** Done

## Acceptance criteria

See E4.1 in [prd.md](../prd.md); why polling rather than pushing is in [ideas/realtime-push.md](../ideas/realtime-push.md).

## Tasks

- [x] `FeedTiming`: the time of the last feed (moved from the poll command) and when the next version should be recorded: the next whole interval after the last publication, plus a second to serve it and one to record it
- [x] The positions endpoint sends `Next-Version-At`, on `200` and `304`
- [x] The live store waits until then, plus up to a second at random; every 5 s when the server says nothing, when the time has passed or is more than a minute away, when the expected version is late (a `304` to a request sent for it) and after a failed request
- [x] Tests: the header on `200` and `304`, and its absence before the first feed; in the browser, no request before the expected time where the usual interval would have asked three times
- [x] PRD: E4 and M2 replanned around polling; pushing in *Later*; the study in `docs/ideas`
- [x] Fix: the poll read the publication time back from Redis as text and stopped waiting for it (18.5 s late locally since story 028, before it reached the server)

## Notes

- **A time, not a delay.** `Next-Version-At` is a time in seconds since the epoch; the page subtracts the answer's `Date`. The phone's clock plays no part, and an answer kept in a cache (E4.2) still gives the right wait, since nginx writes a fresh `Date` on every answer it sends.
- **Spread over a second.** Every page asking at the expected time would make the server answer them all at once; a random delay of up to a second spreads them.
- **A late provider.** When the request sent at the expected time gets a `304`, the version has not come yet: the page asks again after the usual 5 s rather than a whole feed interval later. At night, when positions rarely change, this makes about two requests per feed interval instead of four.
- **Measured locally**, one page on the live TBM feed for 5 minutes at midday on 2026-10-25: **201 requests an hour instead of 720**, and each of the 15 brought a new version (no `304`). The page asked for a version 1.0 s after it was recorded on average (0.4 to 1.6 s: the random spread and the answer's time), where asking every 5 s waited 2.5 s on average.
- **Redis gives numbers back as text.** Laravel stores an integer in Redis unserialised, so the publication time came back as `"1792932342"` and `is_int` refused it: the poll no longer waited for the publication. The array store of the tests keeps the integer; a test now puts the text in the cache.
