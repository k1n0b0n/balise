# Story 020: Weekly version upkeep

- **PRD:** E1.3f
- **Status:** Review

## Acceptance criteria

See E1.3f in [prd.md](../prd.md); the rule of one place per version is ADR-0020 point 3.

## Tasks

- [x] `scripts/raise-versions.php`: the version each platform runs, read from its one place and checked against the places that mention it; the newer version whose official images are all published; the rewrite of those places
- [x] Tests on recorded answers of Docker Hub and nodejs.org (`tests/fixtures/versions`): nothing to raise, PostgreSQL and Redis by majors, PHP once both its images exist, Node by LTS lines only, a place that no longer matches
- [x] `.github/workflows/versions.yml`: every Monday, one pull request per platform with the release App's token; a branch that already raises to the version is left alone
- [ ] First run by hand (`workflow_dispatch`) once merged: nothing to raise before 2026-10-28, then Node 26

## Notes

- **Sources.** A version is offered once every tag the images use exists on Docker Hub (`8.6-fpm-alpine` and `8.6-cli-trixie` for PHP, `19-alpine` for PostgreSQL, `26-alpine` and `26-trixie-slim` for Node): asking for a tag by name answers 200 or 404, where listing every tag would page through thousands. PHP climbs minor by minor, then to the next major; PostgreSQL and Redis major by major, since their tags already float within one; Node goes to the major of the newest release that nodejs.org lists as LTS, so 25 is never proposed and 26 is on 2026-10-28.
- **The one place, and the mentions.** The images run the version of `docker/php/Dockerfile` (PHP, Node) or `compose.yaml` (PostgreSQL, Redis, and the Vite service's Node). The raise also rewrites what readers see: the stack line and the production build command of `AGENTS.md`, the README badge. Every pattern must match, so a line rewritten by hand fails the run instead of going stale. The Dockerfile's header no longer names PHP's version. Boost's guidelines in `AGENTS.md` name it too, between tags only `boost:update` writes.
- **What the raise does not do.** A new PHP major also needs Composer's `php` constraint: CI fails on the pull request until a commit on its branch raises it. A PostgreSQL major is moved by the deploy, by dump and restore (ADR-0020 point 7).
- **Branches.** `versions/<platform>`, one per platform, opened as drafts like every code pull request: a newer version force-pushes it, which replaces the commits added to it; the same version leaves it alone, so the checks do not run again every week and a pull request closed without merging stays closed.
- **No build.** The script needs nothing but PHP and curl, so the workflow runs it with the runner's PHP (8.3 on `ubuntu-24.04`); it avoids PHP 8.4's functions for that.
