# ADR-0014: Advisory AI review, CodeRabbit

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

- One maintainer reviews every diff (ADR-0004), most of it written with an AI agent (`docs/README.md`). A second reader catches what the author's agent and the maintainer both miss.
- The PRD's *Later* planned to evaluate an AI review bot once there is code; E2.1 is the first story with application code.
- The repository is public: the code a bot reads is already published.
- Budget: free (ADR-0012).

## Decision

1. **CodeRabbit**, GitHub App installed on this repository only, free for public repositories.
2. **Advisory, never blocking:** not a required status check; no "request changes". The maintainer's review of the diff stays the gate.
3. **Configured in `.coderabbit.yaml`:** quiet profile, English, project rules per path (ingestion boundary, position model, RGAA and RGESN), `AGENTS.md` read as guidelines. Linters already run by `composer qa` with the project's configuration (PHPStan, plus PHP_CodeSniffer and PHPMD that would contradict Pint) are disabled in the bot.
4. **After the human review, on code only:** pull requests open as drafts, which the bot skips; the maintainer reviews first, marks the pull request *Ready for review*, and one bot review is requested with `@coderabbitai review`. Documentation-only pull requests, grouped per working session, are reviewed by the maintainer alone.
5. **One bot at a time.** After four pull requests reviewed, its findings are compared with what the maintainer caught; Greptile is tried only if CodeRabbit misses real issues.

## Alternatives considered

| Option | Why not |
|---|---|
| Greptile | Indexes the whole repository, but free for open source only on application, otherwise one developer and 50 credits a month, each new commit billed again. Kept as the next candidate |
| CodeRabbit and Greptile together | Duplicate comments on the same lines; the maintainer sorts twice the noise |
| A Claude Code review action | Needs an API key and paid usage |
| No bot | Keeps one reader for agent-written code |

## Consequences

- Each pull request gets a summary and inline comments within minutes; some will be wrong, and replying in the thread tunes the bot (its learnings).
- A third-party service has read access to the repository; removing the app undoes the decision.
- The bot's settings are reviewed like code, in `.coderabbit.yaml`.
- The open-source plan allows 1 to 10 reviews per hour depending on the repository's stars (likely 1 for now), 100 to 300 files per review, and, under 10 stars, reviews start only on request. Hence one review per code pull request, never on each commit.
- A green CodeRabbit status only means the bot did not block, not that it reviewed: a rate-limited review reports success. `fail_commit_status` was tried to show such runs red without blocking, and dropped: a rate-limited review still reports success (#28). The bot's "Review limit reached" comment is the signal to watch. Documentation pull requests (`docs:` titles) are skipped by the bot.
