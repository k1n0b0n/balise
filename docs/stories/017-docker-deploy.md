# Story 017: Docker deploy on any Debian or Ubuntu host, proven on local machines

- **PRD:** E1.3d
- **Status:** In progress

## Acceptance criteria

See E1.3d in [prd.md](../prd.md). Decisions in [ADR-0020](../adr/0020-docker-hosting.md).

## Tasks

- [ ] Official base images: every target of `docker/php/Dockerfile` from `php:8.5-fpm-trixie` with the missing extensions, `docker/nginx/Dockerfile` from `nginx:alpine`; the local stack starts and every quality gate passes on them
- [ ] CI in the image: one job builds the `test` image, then style, static analysis, tests, browser tests (after the front-end build), front end and audit run in parallel in it; a final job `ci` needs them all; CI's duration before (2 min 31 s on #57) and after recorded
- [ ] Each version in one place: PHP and Node in the Dockerfile, PostgreSQL and Redis in `compose.yaml`; `versions.yaml`, `scripts/check-versions.php`, its test and the `versions` job removed; the maintainer drops `versions` from the ruleset's required checks
- [ ] Production images: a `production` target (code, Composer without dev packages, built assets, generated classes, `optimize` at start) and the `web` image with `public/`; PostgreSQL's and Redis's versions as labels of `app`
- [ ] Bootstrap `ansible/host.yml` with its templates: Docker, nginx and certbot from the archive when missing, one nginx site and its certificate, the deploy user restricted to the deploy command, `/srv/balise` with the production `compose.yaml`, generated secrets and the deploy script; firewall and unattended upgrades optional; an example inventory, the real one ignored by Git
- [ ] Deploy script: version or `rollback` only, pull, migrations with the new image, restart, `network:import`, health check with automatic return, vhost rendered from the host's template, versions file from the labels, PostgreSQL major moved by dump and restore, three versions kept
- [ ] Release workflow: on `release: published`, build and push both images, then deploy over SSH from the `production` environment, which waits for the maintainer's approval
- [ ] E1.3b's host install removed: `server.yml`, `deploy.yml`, `rollback.yml`, their templates and `scripts/build-release.sh`
- [ ] `docs/deploy.md`: from a fork to a deployed release
- [ ] Proof on three OrbStack machines (fresh Debian 13, fresh Ubuntu 26.04, Debian 13 already running Docker and nginx with another site): bootstrap twice (`changed=0` the second time), two deploys, a rollback, a PostgreSQL move from 17 to 18; the other site and Docker left untouched
- [ ] Closing: story notes, *Current state*, ideas review (step 5)

## Notes
