# Story 026: Line shapes in the database

- **PRD:** E3.8 (a production bug)
- **Status:** Done

## Acceptance criteria

The criteria of E3.8 in [prd.md](../prd.md), in production: `/api/networks/tbm/shapes` answered 404 there since v0.4.0.

## Tasks

- [x] `catalog_imports.shapes` holds the GeoJSON; `NetworkShapes` writes and reads it there; the migration clears the fingerprint, so the next import (the deploy's) downloads the archive again and fills it
- [x] The import downloads the archive again, even unchanged, when the shapes are missing, not only when the fingerprint is
- [x] The endpoints that read the import row leave the shapes out (`find` with columns, `value`)
- [x] Tests: shapes lost behind an unchanged archive are downloaded and written again (the old test answered a new archive, a path that always worked); the file-based tests moved to the column
- [x] ADR-0016 point 3 amended

## Notes

- **Found by an agent's review of the whole code** (2026-10-24), then checked on the server: `/api/networks/tbm/shapes` answered 404. In production, `php`, `queue` and `scheduler` each have their own `storage/`: the deploy's import ran in `php` (unchanged catalogue, a `304`, so nothing written), the morning one in `scheduler`, and every deploy creates new containers. Locally, the bind mount shares `storage/` and hid it.
- **The database rather than a shared volume**: one place for the document and its fingerprint, which no longer disagree; no change to the host's `compose.yaml`, so no Ansible run; 596 KB of text, read only when the fingerprint changed for a browser.
- **Measured locally:** the first import after the migration downloaded the archive and stored 596 KB; the endpoint answers 94 KB compressed, then a bodyless 304; an import of the same archive afterwards does nothing.
