# Story 015: Server configured and deployed by Ansible, on a local machine

- **PRD:** E1.3b
- **Status:** In progress

## Acceptance criteria

See E1.3b in [prd.md](../prd.md). Decisions in [ADR-0019](../adr/0019-server-configuration.md).

## Tasks

- [x] ADR-0019 accepted by the maintainer (Redis installed now, systemd over Supervisor, server-local secrets generated on it); PRD E1.3b criteria completed
- [ ] `versions.yaml` and `compose.yaml`: Redis 8, the version of the Ubuntu 26.04 archive
- [ ] `ansible` service in `compose.yaml` (profile `ops`): `python:3-alpine`, `ansible-core` and collections pinned in `ansible/`
- [ ] `ansible/cloud-init.yaml`: admin user `ubuntu` with the deploy SSH key, the same file Oracle receives in E1.3c
- [ ] Local machine: OrbStack Ubuntu 26.04 arm64, isolated, 4 GB, created from that file; command in `AGENTS.md`
- [ ] Server playbook (`ansible/server.yml`): packages from `packages.txt`, nginx with `docker/nginx/site.conf`, `php.ini`, shared `fpm-pool.conf` on a Unix socket, PostgreSQL 18 with the application's database and user, Redis 8, `APP_KEY` and database password generated once, user `balise`, systemd units for the queue and the scheduler, firewall (SSH, HTTP, HTTPS), unattended upgrades
- [ ] The second run of the server playbook changes nothing, and check mode reports no drift
- [ ] `scripts/build-release.sh`: archive of a build (no dev packages, built assets, generated classes), made in the `test` image
- [ ] Deploy playbook (`ansible/deploy.yml`): upload, `shared/.env` and `storage`, migrate, switch `current`, reload php-fpm, restart the units, keep three releases; the site answers 200 afterwards
- [ ] On the machine: `network:import tbm` runs, the scheduler polls, the map shows live vehicles; one TBM poll timed (E2.3's open criterion, re-measured on Oracle in E1.3c)
- [ ] Closing: story notes, *Current state*, ideas review (step 5)

## Notes

- Ubuntu 26.04's archive has Redis 8.0, not 7 (`apt-cache policy` in `ubuntu:26.04`, 2026-10-20); the application uses no Redis yet (cache, queue and sessions on the database), but the server installs it now, like `compose.yaml` (ADR-0019 point 2).
