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
3. **Review**: the pull request opens as a draft; quality gates green (tests, static analysis, architecture rules), then the maintainer reviews the diff. Once it is marked *Ready for review*, the AI review of the whole pull request is requested once with `@coderabbitai full review` (ADR-0014); valid findings are fixed before merging. A story is one pull request: its story file, the code, the closing of the story and the *Current state* update travel together. A separate `docs:` pull request, opened ready for review and reviewed by the maintainer only, is kept for planning work without code (ADR, PRD, ideas) and for a session that ends without a code pull request; one per session.
4. **Done**: story file updated with what was learned, in the story's pull request before it is marked ready; the maintainer's review and the merge close it.
5. **Ideas review**, in the same pull request:
   - When the story is the last one an idea planned, the idea's study (measures, options rejected and why) moves into the story's notes under `### Study`, the idea file is deleted and its links point to the story. When it is one of several, only the part it implemented moves.
   - Every *Exploring* idea of `ideas/overview.md` gets a verdict: a PRD story with its criteria, the next measure to take, or *Dropped* with the reason. The overview's table and the *Next* list of *Current state* follow.

## Rules

- **ADR mandatory** for any decision that is costly to reverse: a new dependency, a boundary, a data store, a hosting choice. Template: `adr/_template.md`.
- **Scope lives in the PRD.** An idea that is not in the PRD goes to its *Later* section, not into the current story.
- **AI-assisted, human-reviewed.** Code produced with an agent passes the same gates and the same review as any other code.

## Current state

Update this section at the end of every working session.

- **Phase:** 4 (implementation) started; planning items below still open. Stories 001 (E1.1), 002 (E1.2, CI), 003 (E2.1, static GTFS import), 004 (E2.2, live polling), 005 (E2.3, latest positions), 006 (E3.1, map page), 007 (E3.2, vehicles on the map) and 008 (E3.3, vehicle popup), 009 (E3.5, text view beside the map or in a side panel, line catalogue) and 010 (E3.6, pause), 011 (E3.8, every line of the network on the map) and 012 (E3.4, line and vehicle selection) done, with their follow-ups (#48, #49) merged: ingestion (E2) complete, live vehicles on the map locally, with a popup on click and an accessible text view; up to a third of TBM trams can lack a line (unplanned `nt_` trips), E3.7 plans to fill it from TripUpdates. Local stack and CI on Ubuntu 26.04 and PostgreSQL 18.
- **Done:** brief (draft), PRD epics and stories with acceptance criteria for E1.1, E1.2, E1.3 (most) and E2, ADR-0001 to ADR-0017 accepted (summary in `architecture.md`), story 001: Laravel skeleton, Docker stack and `composer qa` (with `laravel/pao` for agents), `main` ruleset and squash-only merges enabled, agent instructions in `AGENTS.md` (shared across harnesses).
- **Next:**
  1. E3.7 (vehicles grouped by line then destination, named "Tram F - DESTINATION"): ADR-0017 accepted; story next, with the coverage of trip updates measured over a day.
  2. Next departures and next stops of a vehicle ([ideas/next-departures.md](ideas/next-departures.md)): decide the brief's scope (timetables are out of the MVP); trip updates may make the storage of stop times ([ideas/network-on-map.md](ideas/network-on-map.md)) unnecessary.
  3. Write acceptance criteria for the rest of E1.3, E1.4 and E1.5.
  4. E1.3 split in three: E1.3a (versions file and check) in story 014; E1.3b (Ansible on a local virtual machine) next; E1.3c (Oracle Cloud) waits for the account. Choose a domain name (needed by E1.4).
  5. Oracle Cloud account: sign-up failed after identity check, support ticket open (2026-10-16); fallback Scaleway or OVH (ADR-0012).
