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
3. **Review**: quality gates green (tests, static analysis, architecture rules), then a human review of the diff.
4. **Done**: merged, deployed, story file updated with what was learned.

## Rules

- **ADR mandatory** for any decision that is costly to reverse: a new dependency, a boundary, a data store, a hosting choice. Template: `adr/_template.md`.
- **Scope lives in the PRD.** An idea that is not in the PRD goes to its *Later* section, not into the current story.
- **AI-assisted, human-reviewed.** Code produced with an agent passes the same gates and the same review as any other code.

## Current state

Update this section at the end of every working session.

- **Phase:** 4 (implementation) started; planning items below still open. Story 001 (E1.1) in review.
- **Done:** brief (draft), PRD epics and stories (no acceptance criteria yet), ADR-0001 to ADR-0007 accepted (feed ingestion ports, database, MIT license, delivery workflow, quality tooling, RGAA and RGESN, local environment), E1.1 acceptance criteria, `main` ruleset and squash-only merges enabled, agent instructions in `AGENTS.md` (shared across harnesses).
- **Next:**
  1. Write acceptance criteria for the other M1 stories (E1.2 to E1.5, E2, E3).
  2. Define the normalised position model (open question in `architecture.md`).
  3. Write the hosting ADR (VPS + Forge + Cloudflare); provision only once something runs locally.
