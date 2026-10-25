# Story 030: Answers kept by nginx until the next version

- **PRD:** E4.2
- **Status:** Done

## Acceptance criteria

See E4.2 in [prd.md](../prd.md); it builds on the expected time of [story 029](029-aligned-polling.md).

## Tasks

- [x] `FeedTiming::answerExpiresAt()`: the next version's time, or 2 s once the expected version is late; the positions endpoint sends it as `X-Accel-Expires: @<time>`, which nginx keeps to itself
- [x] `docker/nginx/site.conf`: a cache for the positions endpoint only, keyed by URL, 16 MB at most in the container, one request to PHP for pages asking at the same moment; the cache's answer in the access log
- [x] Tests: the time sent before the expected time, before the last version's own time, and when the expected version is late; none before the first feed
- [x] Checked locally: `MISS` then `HIT`, `304` from nginx for strong and weak ETags, pages and errors not kept
- [x] Measured locally with 50 simulated pages

## Notes

- **nginx sends the 304s.** PHP's answer depends on the page's `If-None-Match`, so keeping it per URL would hand a `304` to a page that needs the positions. nginx does not pass `If-None-Match` to PHP for the positions: PHP always answers in full, nginx keeps that answer and itself answers `304` to the pages whose ETag matches, weak ones included (gzip makes the ETag weak).
- **Only `X-Accel-Expires` keeps an answer.** nginx ignores PHP's `Cache-Control: no-cache, private`, which stays for browsers and the host's nginx; an answer without `X-Accel-Expires` (an unknown network, no feed read yet) is not kept. Other routes skip the cache altogether (`BYPASS` in the log), so a page with a session is never kept.
- **A fresh `Date`** (found by CodeRabbit): nginx passes PHP's `Date` on, and kept it with the answer, so a `HIT` repeated the time of the `MISS` and pages, which count their wait from it, would have waited too long. nginx now hides PHP's `Date` and writes its own, on hits and `304`s alike.
- **A late version.** Once the expected time has passed without a newer feed, an answer is kept 2 s: the poll's retry records it about 2 s after the expected time, and the page asks again 5 s after a `304`, so it comes as without the cache.
- **Measured locally**, 50 pages following the live TBM feed as the live store does, for 2 minutes on 2026-10-25: 300 requests, of which **16 reached PHP** (7 `MISS`, 9 `EXPIRED`) for 7 versions; without the cache all 300 do. Two to three per version rather than one, because a page that has just opened asks without `since`, and a key comes back from one version to the next (`EXPIRED`).
- **Not on the server's numbers yet**: with one or two visitors, the cache changes nothing; it matters from the moment pages at the same version are many.
