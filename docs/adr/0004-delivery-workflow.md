# ADR-0004: Delivery workflow, from pull request to release deploy

- **Status:** Accepted
- **Date:** 2026-10-14
- **Amended:** 2026-10-16: hosting moved to an Oracle Cloud server provisioned with OpenTofu and Ansible (ADR-0012); deploys run from GitHub Actions
- **Amended:** 2026-10-21: point 5 deploys Docker images on a server prepared by Ansible (ADR-0020)

## Context

- Single maintainer, public repository on a personal GitHub account.
- Every change must pass the quality gates and a human review of the diff (agent rules).
- Hosting is one server provisioned as code (ADR-0012). Deploying on every push would ship code that CI has not yet passed, and pulling the branch head on the server would deploy something other than a tagged version.
- Reviewers should see versions and a changelog, not only a stream of commits.

## Decision

1. **`main` is protected by a ruleset:** pull request required, required status check `ci`, linear history, no force push, no deletion, no bypass actors (the maintainer included). Required approvals: 0, since GitHub forbids approving one's own pull request; the review is the maintainer reading the diff.
2. **Squash merge only**, with head branches deleted after merge. The pull request title becomes the commit message and is checked against Conventional Commits in CI.
3. **Only the maintainer merges.** On a personal account this holds as long as no collaborator is added; co-maintainers would require moving the repository to an organisation.
4. **Versions and changelog by release-please.** It keeps a release pull request up to date from the Conventional Commits; merging it creates the tag and the GitHub release. It runs with a token from a dedicated GitHub App, because pull requests opened with the default `GITHUB_TOKEN` do not trigger CI and would be blocked by the required check.
5. **Deploy on release only.** Since ADR-0020: a workflow on `release: published` builds the `app` and `web` images, pushes them to the GitHub Container Registry, then calls the server's deploy command over SSH with a key restricted to it (pull, migrate, restart, import, health check). Before it: a workflow on `release: published` checks out the release tag, builds it on the runner (`composer install --no-dev`, `npm run build`), and runs an Ansible playbook that uploads the archive to a new release directory, runs migrations and switches a `current` symlink, then reloads php-fpm and restarts the workers. The SSH key is a repository secret. The server needs no Node and no Git checkout.

## Alternatives considered

| Option | Why not |
|---|---|
| Direct pushes to `main` | No CI gate before code lands, no place for the diff review |
| Continuous deployment on every merge to `main` | Simpler, but no versions or changelog, and no control over when the demo changes |
| Deploy on every push to `main` | Deploys even when CI is red, and skips the release pull request |
| Build on the server (`git pull`, `composer install`, `npm run build`) | Node and build tools on the server, and a broken build breaks the live site |
| Manual tags and releases | Error-prone; release-please derives versions and changelog from commits already in the required format |
| semantic-release | Publishes on every merge with no release pull request to review; heavier configuration |

## Consequences

- Nothing reaches production without a green CI and a merged release pull request.
- The ruleset can be enabled now; the required check `ci` is added once E1.2 ships.
- One GitHub App to create and maintain for release-please.
- Hotfixes follow the same path: pull request, then release pull request.
