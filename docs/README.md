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

- **Phase:** 1 to 3 (planning) in progress. No application code yet.
- **Done:** brief (draft), PRD epics and stories (no acceptance criteria yet), ADR-0002 accepted.
- **Next:**
  1. Accept or amend ADR-0001 (status: Proposed).
  2. Write acceptance criteria for the M1 stories (E1, E2, E3).
  3. Define the normalised position model (open question in `architecture.md`).
  4. Write the hosting ADR (VPS + Forge + Cloudflare); provision only once something runs locally.
