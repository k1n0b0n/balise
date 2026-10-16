# Workflow (BMAD-lite)

A trimmed-down version of the [BMAD Method](https://github.com/bmad-code-org/BMAD-METHOD): the same planning artefacts, without the full set of agent personas.

## Phases

| # | Phase | Artefact | Done when |
|---|---|---|---|
| 1 | Analysis | `brief.md` | Problem, audience, MVP scope and out-of-scope are agreed |
| 2 | Planning | `prd.md` | Every MVP story has testable acceptance criteria |
| 3 | Solutioning | `architecture.md` + `adr/` | Every structuring decision has an accepted ADR |
| 4 | Implementation | `stories/NNN-*.md` | Story loop below, one story at a time |

## Story loop

1. **Draft**: copy `stories/_template.md`, link the PRD story, list the tasks.
2. **Dev**: implement on a branch, tests first where it pays.
3. **Review**: the pull request opens as a draft; quality gates green (tests, static analysis, architecture rules), then the maintainer reviews the diff. Once it is marked *Ready for review*, the AI review is requested once with `@coderabbitai review` (ADR-0014); valid findings are fixed before merging. Documentation changes of a working session go into one pull request, reviewed by the maintainer only.
4. **Done**: merged, deployed, story file updated with what was learned.

## Rules

- **ADR mandatory** for any decision that is costly to reverse: a new dependency, a boundary, a data store, a hosting choice. Template: `adr/_template.md`.
- **Scope lives in the PRD.** An idea that is not in the PRD goes to its *Later* section, not into the current story.
- **AI-assisted, human-reviewed.** Code produced with an agent passes the same gates and the same review as any other code.

## Current state

Update this section at the end of every working session.

- **Phase:** 4 (implementation) started; planning items below still open. Stories 001 (E1.1), 002 (E1.2, CI) and 003 (E2.1, static GTFS import) done; next story: E2.2, starting with the protobuf library ADR. Local stack and CI on Ubuntu 26.04 and PostgreSQL 18.
- **Done:** brief (draft), PRD epics and stories with acceptance criteria for E1.1, E1.2, E1.3 (most) and E2, ADR-0001 to ADR-0014 accepted (feed ingestion ports, database, MIT license, delivery workflow, quality tooling, RGAA and RGESN, local environment, CSS approach, Laravel Boost, UI stack, map library, hosting, position model, AI review bot), story 001: Laravel skeleton, Docker stack and `composer qa` (with `laravel/pao` for agents), `main` ruleset and squash-only merges enabled, agent instructions in `AGENTS.md` (shared across harnesses).
- **Next:**
  1. Write acceptance criteria for the other M1 stories (rest of E1.3, E1.4, E1.5, E3).
  2. Choose a domain name (needed by E1.3 and E1.4); provision only once something runs locally.
  3. Oracle Cloud account: sign-up failed after identity check, support ticket open (2026-10-16); fallback Scaleway or OVH (ADR-0012).
