# Story 016: Releases and version upkeep

- **PRD:** E1.3c
- **Status:** Draft

## Acceptance criteria

See E1.3c in [prd.md](../prd.md).

## Tasks

- [x] PRD: E1.3c split in two, so that releases and version upkeep, which need no server, come before Oracle (E1.3d); the weekly raise reworded to fit the archive rule of ADR-0019 (both approved by the maintainer)
- [ ] GitHub App `balise-release` created and installed by the maintainer (contents, pull requests and workflows: read and write; workflows because a raise edits `.github/workflows/ci.yml`); its id and private key as repository secrets
- [ ] release-please: workflow on pushes to `main`, `simple` release type, first version 0.1.0, changelog from the Conventional Commits
- [ ] Weekly raise: a script reads `versions.yaml`, asks the sources (Node release schedule, Ubuntu's LTS releases, the new release's archive) and rewrites the files named by `scripts/check-versions.php`; a workflow opens or updates one pull request per raise
- [ ] Tests of the raise rules on recorded answers (newer Node LTS, newer Ubuntu LTS, nothing to raise)
- [ ] Closing: story notes, *Current state*, ideas review (step 5)

## Notes

- **The raise criterion clashed with the archive rule.** PHP, PostgreSQL and Redis come from the Ubuntu archive (ADR-0007, ADR-0019), which keeps one version of each for the life of a release: raising PHP alone would need a third-party repository. They move with Ubuntu, in one pull request. The maintainer kept the rule over upstream repositories (PGDG, packages.redis.io, Ondřej Surý's PHP repository): each would raise its version alone, but the Docker images would need the same repositories, three signing keys would be pinned, and PHP's repository rests on one volunteer; the cost is PHP 8.6 (November 2026) waiting for Ubuntu 28.04 (April 2028), while 8.5 stays supported until 2029. Node is the only version that moves alone (official image, LTS lines).
- **First real case next week:** Node 26 becomes LTS on 2026-10-28 (Node release schedule); `versions.yaml` has 24.
