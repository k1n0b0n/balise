# Story 028: The server's stores and guards

- **PRD:** E1.8
- **Status:** Done

## Acceptance criteria

See E1.8 in [prd.md](../prd.md); the decisions are [ADR-0021](../adr/0021-redis-and-counted-versions.md).

## Tasks

- [x] `redis` for `CACHE_STORE`, `QUEUE_CONNECTION` and `SESSION_DRIVER` in `.env.example` and `env.j2`
- [x] `position_versions`, counted in `RecordPositions`' transaction; `PositionsUpdated` carries the version; `PositionChangeLog` checks that versions follow one another
- [x] `trustHosts()` beside `trustProxies(at: '*')`; HSTS in the host's TLS server block
- [x] The deploy script refuses a second rollback in a row (`rolled-back`, cleared by the next deploy); `docs/deploy.md`
- [x] Tests: a version the change log missed gives the whole state; a browser one version further than the log keeps gets it too; the host check, shown failing without `trustHosts()`; the write path's query count with the version's upsert
- [x] Dry run of the bootstrap on the local Ubuntu machine; `sh -n` on the deploy script
- [x] [ideas/page-without-livewire.md](../ideas/page-without-livewire.md), the other half of review point 15

## Notes

- **Locally, after the switch:** the change log, the feed's ETag and publication time are in Redis (database 1, the cache's), sessions in database 0; `position_versions` counts the writes; the map page and the positions endpoint answer as before.
- **Why counted versions** (review point 10): with the cache in Redis, the change log can no longer join the positions' transaction. Versions were milliseconds, so a missing one could not be told from a pause; numbered by PostgreSQL in the write's own transaction, they have no gap, and a gap in the log means a lost entry. This also covers a Redis restart that lost the last entries, which a shared transaction would not have.
- **A test changed meaning**: "a browser that fell too far behind" was one exactly as many versions behind as the log keeps. With numbered versions nothing is missing for it, so it now gets a delta; the test now falls one further.
- **No `default_server` on the host** (review point 8): the host's nginx already sends other names to its own default site, and `server_name` keeps them away from ours; the risk was the application building links from any forwarded host, which `trustHosts()` closes. Laravel leaves it out in local development and tests, so the test switches the environment to production. The deploy's health check already sends the site's own host.
- **HSTS for a year, without `includeSubDomains`**: the domain's other names are not this project's.
- **The dry run** on the local Ubuntu machine changes two files, the deploy script (the diff shows the refusal) and `app.env` (hidden, it holds secrets); the local machines have no certificate, so HSTS shows only on the server. The Debian machine had been reset: a dry run stops on a host never prepared (story 018).
- **On the server**: the maintainer's Ansible run writes `app.env`, the deploy script and the nginx site; the next deploy recreates the containers on Redis.
