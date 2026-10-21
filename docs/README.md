# Workflow (BMAD-lite)

A trimmed-down version of the [BMAD Method](https://github.com/bmad-code-org/BMAD-METHOD): the same planning artefacts, without the full set of agent personas.

## Phases

| # | Phase | Artefact | Done when |
|---|---|---|---|
| 1 | Analysis | `brief.md` | Problem, audience, MVP scope and out-of-scope are agreed |
| 2 | Planning | `prd.md` | Every MVP story has testable acceptance criteria |
| 3 | Solutioning | `architecture.md` + `adr/` | Every structuring decision has an accepted ADR |
| 4 | Implementation | `stories/NNN-*.md` | Story loop below, one story at a time |
| — | Ideas | `ideas/*.md`, indexed in `ideas/overview.md` | Not a phase: studies that feed the PRD; status Exploring, Planned (→ PRD ids) or Dropped (with the reason). Only open and dropped ideas stay in the folder (see step 5) |

## Story loop

1. **Draft**: copy `stories/_template.md`, link the PRD story, list the tasks.
2. **Dev**: implement on a branch, tests first where it pays.
3. **Review**: the pull request opens as a draft; quality gates green (tests, static analysis, architecture rules), then the maintainer reviews the diff. Marking it *Ready for review* starts the AI review of the whole pull request on its own, once (ADR-0014); valid findings are fixed before merging. A story is one pull request: its story file, the code, the closing of the story and the *Current state* update travel together. A separate `docs:` pull request, opened ready for review and reviewed by the maintainer only, is kept for planning work without code (ADR, PRD, ideas) and for a session that ends without a code pull request; one per session.
4. **Done**: story file updated with what was learned and set to *Done*, *Current state* updated, in the story's pull request before it is marked ready; the maintainer's review and the merge close it. Never a `docs:` pull request to close a story after its merge: a check that can only run after the merge (a workflow started by hand, a deploy) is written as a follow-up in *Current state*, and its result goes to the maintainer, not into a new pull request.
5. **Ideas review**, in the same pull request:
   - When the story is the last one an idea planned, the idea's study (measures, options rejected and why) moves into the story's notes under `### Study`, the idea file is deleted and its links point to the story. When it is one of several, only the part it implemented moves.
   - Every *Exploring* idea of `ideas/overview.md` gets a verdict: a PRD story with its criteria, the next measure to take, or *Dropped* with the reason. The overview's table and the *Next* list of *Current state* follow.

## Rules

- **ADR mandatory** for any decision that is costly to reverse: a new dependency, a boundary, a data store, a hosting choice. Template: `adr/_template.md`.
- **Scope lives in the PRD.** An idea that is not in the PRD goes to its *Later* section, not into the current story.
- **AI-assisted, human-reviewed.** Code produced with an agent passes the same gates and the same review as any other code.

## Current state

Update this section at the end of every working session.

- **Phase:** 4 (implementation) started; planning items below still open. Stories 001 (E1.1), 002 (E1.2, CI), 003 (E2.1, static GTFS import), 004 (E2.2, live polling), 005 (E2.3, latest positions), 006 (E3.1, map page), 007 (E3.2, vehicles on the map) and 008 (E3.3, vehicle popup), 009 (E3.5, text view beside the map or in a side panel, line catalogue) and 010 (E3.6, pause), 011 (E3.8, every line of the network on the map) 012 (E3.4, line and vehicle selection), 013 (E3.7, vehicles grouped by line and destination), 014 (E1.3a, platform versions in `versions.yaml`, checked in CI), 015 (E1.3b, server configured and deployed by Ansible on a local machine), 016 (E1.3c, releases by release-please and a GitHub App; v0.1.0 published) 017 (E1.3d, Docker images from the official Alpine ones, CI split into parallel jobs in them, a release deployed and rolled back on three local machines) 018 (E1.3e, live on the maintainer's server: v0.3.0 deployed by the release workflow) 019 (E3.4, the line selector lists the lines that run, by their long name, narrowed by a mode selector) done, and 020 (E1.3f, weekly version upkeep: one pull request per newer PHP, PostgreSQL, Redis or Node LTS, every Monday) done, with their follow-ups (#48, #49) merged: ingestion (E2) complete, live vehicles on the map, in production since v0.3.0, with a popup on click and an accessible text view; a few TBM trams a day lack a line (unplanned `nt_` trips, 0.03 % of positions), shown as "Ligne inconnue" until the trip updates port. Local stack, CI and production images on `php:8.5-fpm-alpine` (the browser tests on `php:8.5-cli-trixie`) and PostgreSQL 18.
- **Done:** brief (draft), PRD epics and stories with acceptance criteria for E1.1, E1.2, E1.3 (most) and E2, ADR-0001 to ADR-0020 accepted, ADR-0012 superseded, ADR-0017 superseded by ADR-0018 (summary in `architecture.md`), story 001: Laravel skeleton, Docker stack and `composer qa` (with `laravel/pao` for agents), `main` ruleset and squash-only merges enabled, agent instructions in `AGENTS.md` (shared across harnesses).
- **Next:**
  1. Node 26 becomes LTS on 2026-10-28: the `versions` workflow should open its pull request on Monday 2026-11-02 (or run it by hand). Then, from story 018: OPcache for the CLI and a lighter `RecordPositions` (PRD *Later*), and an AAAA record once IPv6 is wanted (the site already listens on it).
  2. Next departures and next stops of a vehicle ([ideas/next-departures.md](ideas/next-departures.md)): decide the brief's scope (timetables are out of the MVP); trip updates may make the storage of stop times ([ideas/network-on-map.md](ideas/network-on-map.md)) unnecessary; shared foundations and an order in [ideas/overview.md](ideas/overview.md).
  3. Write acceptance criteria for the rest of E1.3, E1.4 and E1.5.
  4. Raw recording (story 013) continues outside the repository (`~/Code/k1n0b0n/balise-samples`) until 2026-10-27; E5.1 brings it in.
