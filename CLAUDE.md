# balise — agent instructions

- Start every session by reading the *Current state* section of `docs/README.md`, and update it before ending.
- Follow the BMAD-lite workflow in `docs/README.md`. Work on one story at a time, from its file in `docs/stories/`.
- Never implement anything that is not in `docs/prd.md`. Propose it for the *Later* section instead.
- A decision that is costly to reverse needs an ADR in `docs/adr/` before code.
- Respect the boundary from ADR-0001: only the ingestion layer knows about GTFS, GTFS-RT, SIRI or any other feed format.
- All code, docs, comments and commit messages in English. Conventional Commits.
- A story is done only when tests, static analysis and architecture tests pass and the diff has been reviewed by a human.
