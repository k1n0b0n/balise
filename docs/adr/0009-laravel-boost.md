# ADR-0009: Agent guidelines and MCP server, Laravel Boost

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

- The code is written with AI agents (`docs/README.md`), and models mostly know older APIs of Livewire, Pest and Laravel.
- The Laravel skeleton ships a bootstrap `AGENTS.md` that tells the agent to install PHP on the host and then Laravel Boost. It was dropped in E1.1: no PHP on the host (ADR-0007), and Boost is a dependency that needs an ADR.
- Laravel Boost generates guidelines and skills matching the installed package versions, and runs an MCP server (application info, database schema and queries, version-matched documentation search).
- Our own rules (`AGENTS.md`) must stay the reference: one file shared by every harness, `CLAUDE.md` only imports it.

## Decision

1. **`laravel/boost` as a dev dependency**, configured by `boost.json` and `config/boost.php`.
2. **Guidelines go into `AGENTS.md`**, between Boost's own tags, below our rules; Claude Code is pointed there too instead of `CLAUDE.md`. Our rules and the ADRs win over a generated guideline.
3. **Skills are generated, not versioned:** `.claude/skills/` is ignored, the setup service and `composer update` regenerate them with `boost:update`.
4. **Excluded:** Laravel Cloud guidelines and skill (deployment is Forge, ADR-0004), Boost project rules and the `infer-conventions` skill (decisions live in ADRs, conventions in `AGENTS.md`).
5. **The MCP server runs inside the PHP container:** `.mcp.json` calls `docker compose exec -T php php artisan boost:mcp`.

## Alternatives considered

| Option | Why not |
|---|---|
| Hand-written guidelines only | Version-specific API knowledge to write and maintain ourselves, for every package upgrade |
| Keep the skeleton's bootstrap `AGENTS.md` | Installs PHP on the host, contradicts ADR-0007 |
| Version the generated skills | Vendor content in the diff, drifts from the installed Boost version |

## Consequences

- Agents get guidelines and documentation that match the installed versions; `composer update` keeps them in sync.
- `AGENTS.md` grows from about 50 to about 180 lines, most of it generated; the generated commands assume PHP on the host and are translated by a note in our part.
- The MCP log tools (`last-error`, `read-log-entries`) find nothing: logs go to stderr (ADR-0007). `docker compose logs` replaces them.
- A dependency update can change `AGENTS.md`; the diff shows it in review.
