# Story 016: Releases

- **PRD:** E1.3c
- **Status:** Draft

## Acceptance criteria

See E1.3c in [prd.md](../prd.md).

## Tasks

- [x] PRD: E1.3c split in two, so that releases and version upkeep, which need no server, come before the server (E1.3d, E1.3e); the weekly raise reworded to fit the archive rule of ADR-0019 (both approved by the maintainer)
- [x] GitHub App `balise-release` created and installed by the maintainer (contents, pull requests and workflows: read and write; workflows because a raise edits `.github/workflows/ci.yml`); its private key as the secret `RELEASE_APP_PRIVATE_KEY`, its client id as the variable `RELEASE_APP_CLIENT_ID` (`create-github-app-token` v3 deprecates the app id)
- [ ] release-please: workflow on pushes to `main` (`.github/workflows/release.yml`), `simple` release type (`version.txt`, `CHANGELOG.md`), first version 0.1.0 set by `initial-version` (release-please's default first version is 1.0.0), release pull request titled `chore: release x.y.z`; proven once merged by the first release pull request
- [ ] Closing: story notes, *Current state*, ideas review (step 5)

## Notes

- **The raise criterion clashed with the archive rule** (since replaced, see the last notes). PHP, PostgreSQL and Redis come from the Ubuntu archive (ADR-0007, ADR-0019), which keeps one version of each for the life of a release: raising PHP alone would need a third-party repository. They move with Ubuntu, in one pull request. The maintainer kept the rule over upstream repositories (PGDG, packages.redis.io, Ondřej Surý's PHP repository): each would raise its version alone, but the Docker images would need the same repositories, three signing keys would be pinned, and PHP's repository rests on one volunteer; the cost is PHP 8.6 (November 2026) waiting for Ubuntu 28.04 (April 2028), while 8.5 stays supported until 2029. Node is the only version that moves alone (official image, LTS lines).
- **First real case next week:** Node 26 becomes LTS on 2026-10-28 (Node release schedule); `versions.yaml` has 24.
- **Hosting changed while drafting (ADR-0020):** Oracle's sign-up failed, so the application will run as Docker images on the maintainer's server, prepared by an Ansible bootstrap; E1.3d proves it on local machines, E1.3e puts it live. This story is unchanged: release-please and the App still produce the release that E1.3d deploys.
- **The Ubuntu coupling is gone (ADR-0020 point 2):** once the images start from the official PHP and nginx images, PHP no longer waits for an Ubuntu release; PostgreSQL and Redis already ran from their official images. Every version moves alone. The weekly raise reads the tags of the official images, so it moves to its own step, E1.3f, after E1.3d has moved the Dockerfiles; this story covers release-please only.
- **Merging stays the maintainer's call:** release-please never merges its pull request. A second ruleset requiring one approval, bypassed by the admin role, was meant to stop a stolen App key from merging; it was dropped the same day: GitHub showed no bypass on the maintainer's own pull requests (both rulesets required a pull request, and `main`'s has no bypass), and the key could still tag a commit it pushed and publish a release. The guard moves to the deploy: the `production` environment waits for the maintainer's approval (ADR-0020 point 7, E1.3d).
