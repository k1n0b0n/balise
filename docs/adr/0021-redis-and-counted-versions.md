# ADR-0021: Redis for cache, queue and sessions; position versions counted by PostgreSQL

- **Status:** Accepted
- **Date:** 2026-10-25

## Context

- Redis has run in every environment since E1.1 (ADR-0007, ADR-0020), but the cache, the queue and the sessions were all in PostgreSQL (`database` drivers), so it held nothing; AGENTS.md already called the latest positions "hot data for Redis/memory".
- The cache holds the positions' change log, read on every request of every open map page (every 5 s), and the feed's ETag and publication time, written on every poll. The queue has no job yet; its worker polled the `jobs` table every few seconds. Every visit to the map page wrote a session row, for Livewire's CSRF token.
- The change log is written after the positions are committed (story 007). A code review (2026-10-24, story 027, point 10) found that a change log write that fails after the commit leaves browsers asking `?since=` without the removals of that version: ghost vehicles until a reload. With the cache in PostgreSQL, the write could have joined the transaction; in Redis it cannot.

## Decision

1. **The `redis` drivers for the cache, the queue and the sessions**, locally and on the server (`.env.example`, `env.j2`). The tests keep `array` and `sync`. Everything there may be lost: the change log answers full positions when it cannot answer a delta, the feed's ETag and publication time are learnt again on the next poll, a lost session only renews the CSRF token.
2. **Versions counted by PostgreSQL**: `RecordPositions` adds one to the network's row of `position_versions` in the transaction that writes the positions, and `PositionsUpdated` carries that number. Versions follow one another without a gap.
3. **The change log refuses a delta across a gap**: for `?since=v`, the versions after `v` must be `v + 1`, `v + 2`… up to the current one; a version the log missed (a failed write, a Redis restart that lost the last ones, an entry trimmed away) makes it answer the whole state instead. The newest version missing cannot be seen until the next one is written: until then, browsers keep the state before it, one poll late (about 20 s in the day), then get the whole state. Seeing it at once would mean reading PostgreSQL's version on every request of every map page, which this decision avoids; found by CodeRabbit on #83 and accepted.

## Alternatives considered

| Option | Why not |
|---|---|
| Keep the cache in PostgreSQL, the change log inside the write's transaction | Every map page's request, every 5 s, reads PostgreSQL; and Redis keeps running for nothing |
| Drop Redis and the queue worker | The fewest moving parts today, but the first queued job or pushed update (E4) would bring them back |
| Versions as milliseconds of the write (until now) | Never come back after a cache flush, but a missing one cannot be seen |
| Clear the change log when its write fails | A Redis that is down cannot be cleared either, and comes back with the old log |

## Consequences

- No more writes to PostgreSQL for sessions or the cache's hot keys; one more small upsert per poll that changes something.
- A browser that fell exactly as many versions behind as the log keeps now gets a delta rather than everything again, since nothing is missing.
- The rows left in the `cache`, `sessions` and `jobs` tables stay until a later cleanup; nothing reads them.
- The server takes the drivers from `app.env`, written by the bootstrap: the Ansible run, then the next deploy, which recreates the containers, switch it over.
