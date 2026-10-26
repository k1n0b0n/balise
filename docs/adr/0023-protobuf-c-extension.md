# ADR-0023: The protobuf C extension in every PHP image

- **Status:** Accepted
- **Date:** 2026-10-26

## Context

- E8 reads TBM's trip updates: 2.7 MB at midday, about 300 KB at night, a new version about every 20 s, and the producer ignores `If-None-Match` ([ideas/next-departures.md](../ideas/next-departures.md)).
- ADR-0015 point 1 chose Google's pure-PHP runtime and kept the C extension as the fallback "if it does not" stay within budget. Measured on a recorded midday payload: 1.2 to 1.4 s and 344 MB peak memory in pure PHP; 0.03 s and 11 MB with the extension `protobuf` 5.36.2, built from PECL in 18 s on Alpine. In pure PHP, one request would need about three times php-fpm's memory limit, and a click would wait more than a second for decoding alone.
- PHP runs only in the project's Docker images (ADR-0007, ADR-0020): Ubuntu's missing package no longer matters, and the server needs no compiler. `docker/php/install-extensions.sh` already builds `redis` from a pinned, checksummed source for the `runtime`, `dev` and `test` targets.
- The extension's source is published only on PECL (`protobuf-5.36.2.tgz`); Google's GitHub releases carry no PHP archive.
- The generated classes (ADR-0015 point 2) work unchanged with either runtime: the extension defines the `Google\Protobuf` classes, and Composer's runtime is then never loaded.

## Decision

1. **The extension `protobuf` is built into every PHP image** by `install-extensions.sh`, from PECL's archive pinned by version and SHA-256, like `redis`: production, local and CI decode the same way. This supersedes point 1 of ADR-0015; its points 2 to 4 stand.
2. **Its version equals the Composer runtime's** (`google/protobuf` in `composer.lock`), checked by a test. Dependabot raises the Composer runtime; the test then fails until the script's version and checksum follow, in the same pull request.
3. **The trip updates driver requires the extension** and says so when it is missing, rather than exhausting memory. The vehicle feed keeps working without it.
4. **Composer's `google/protobuf` stays**: Dependabot follows it, and it documents the version the classes were generated against.

## Alternatives considered

| Option | Why not |
|---|---|
| Pure-PHP runtime, a higher memory limit for the reads | 344 MB and more than a second per read, in a web request: beyond E2.3's one-second budget, and a limit raised for every php-fpm worker |
| The extension built once and copied as a `.so` | Must match PHP's exact build and architecture (the server's and each workstation's); the image build already does it in 18 s |
| Decoding in another process (Go, Rust, Python) | A second language and runtime to deploy and review, against "plain Laravel outside ingestion" (ADR-0001) |
| Trip updates in another format (JSON, SIRI Lite) | TBM publishes trip updates in GTFS-RT protobuf only |

## Consequences

- About 20 s more per image build; the CI's image cache absorbs it.
- A native crash in decoding takes down one php-fpm worker or one command, which restarts; the pure-PHP runtime could only fail with a PHP error.
- Raising the protobuf runtime is no longer only a Composer bump: the same pull request updates the extension's version and checksum, and the test says so.
- The vehicle feed decodes faster too; nothing else changes for it.
