# ADR-0005: Quality tooling, Pint, Larastan and Pest

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

- E1.1 requires formatting, static analysis, tests and architecture tests as quality gates.
- The ADR-0001 boundary (no feed format types outside ingestion) must be enforced by the build, not by review alone.
- The project is a Laravel showcase: code should read like idiomatic Laravel.

## Decision

1. **Pint, `laravel` preset**, for formatting; CI runs `pint --test`. A deliberate choice over PSR-12 / PER so that hand-written code matches the framework and `artisan make` stubs.
2. **Larastan at PHPStan level max** from the first commit, while raising the level is still free.
3. **Pest** for tests, with the `arch` plugin enforcing the ADR-0001 boundary, and browser testing (Pest 4) for the map stories. Test closures are typed; `tests/` may run at a lower PHPStan level.
4. **One entry point, `composer qa`**, running Pint, Larastan and Pest, used both locally and in CI.
5. **No pre-commit hooks:** CI is the source of truth.

## Alternatives considered

| Option | Why not |
|---|---|
| PHP-CS-Fixer directly | Pint is built on it and accepts all its rules through `pint.json`; Pint ships with Laravel and needs no configuration |
| PHPStan without Larastan | Does not understand Eloquent, facades or container magic: false positives, or a level too low to be useful. IDE helper stubs are generated files to maintain |
| PHPUnit | Universal and better analysed by PHPStan, but needs a separate tool (deptrac, PHPat) for architecture rules and has no built-in browser testing |
| Coverage threshold | Encourages hollow tests; architecture tests and acceptance criteria give more value |
| Pre-commit hooks (husky, CaptainHook) | Slow down commits, bypassed with `--no-verify`, duplicate CI |

## Consequences

- Style is never discussed in review.
- Larastan boots the application: it must boot in CI without external services.
- Reviewers from outside Laravel may need a moment to read Pest syntax.
