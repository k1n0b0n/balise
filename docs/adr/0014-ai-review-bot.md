# ADR-0014: Advisory AI review, CodeRabbit

- **Status:** Accepted
- **Date:** 2026-10-16
- **Amended:** 2026-10-18: `@coderabbitai full review`, since `review` only covers the commits added after the last review
- **Amended:** 2026-10-19: a story's documentation travels in its code pull request; `docs:` pull requests only for planning work without code
- **Amended:** 2026-10-21: marking a pull request ready starts the review on its own; `@coderabbitai full review` only when the maintainer asks for another one

## Context

- One maintainer reviews every diff (ADR-0004), most of it written with an AI agent (`docs/README.md`). A second reader catches what the author's agent and the maintainer both miss.
- The PRD's *Later* planned to evaluate an AI review bot once there is code; E2.1 is the first story with application code.
- The repository is public: the code a bot reads is already published.
- Budget: free (ADR-0012).

## Decision

1. **CodeRabbit**, GitHub App installed on this repository only, free for public repositories.
2. **Advisory, never blocking:** not a required status check; no "request changes". The maintainer's review of the diff stays the gate.
3. **Configured in `.coderabbit.yaml`:** quiet profile, English, project rules per path (ingestion boundary, position model, RGAA and RGESN), `AGENTS.md` read as guidelines. Linters already run by `composer qa` with the project's configuration (PHPStan, plus PHP_CodeSniffer and PHPMD that would contradict Pint) are disabled in the bot.
4. **After the human review, on code only:** pull requests open as drafts, which the bot skips; the maintainer reviews first, marks the pull request *Ready for review*, which starts one bot review of the whole pull request (`auto_review` skips drafts, incremental reviews are off); `@coderabbitai full review` is commented only when the maintainer wants another one. A story's documentation (story file, closing, current state) travels in its code pull request and is reviewed with it. Documentation-only pull requests, kept for planning work without code and grouped per working session, are opened ready for review and reviewed by the maintainer alone.
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
- The open-source plan allows 1 to 10 reviews per hour depending on the repository's stars (likely 1 for now), 100 to 300 files per review, and no review of each new commit (`auto_incremental_review` off). Hence one review per code pull request, started when it is marked ready, never on each commit.
- A green CodeRabbit status does not mean a review ran: a rate-limited review reports success, even with `fail_commit_status` (tried and dropped, #28). The bot's "Review limit reached" comment is the signal to watch. The bot skips `docs:` pull requests.
