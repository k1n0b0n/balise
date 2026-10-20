# ADR-0019: Server configuration and deploys with Ansible

- **Status:** Proposed
- **Date:** 2026-10-20

## Context

ADR-0012 configures the server with Ansible from the repository, and ADR-0004 deploys a build with an Ansible playbook from GitHub Actions. E1.3b writes both playbooks and proves them on a local Ubuntu 26.04 machine before any cloud account exists (E1.3c). Several choices are costly to reverse: how Ansible is run, where packages come from, how the application is laid out and run on the server, and where secrets live.

Measured on 2026-10-20 in `ubuntu:26.04`: the archive has nginx 1.28, PHP 8.5.4, PostgreSQL 18 and Redis 8.0 (not 7, the version of `versions.yaml` and `compose.yaml`). The application uses no Redis yet: cache, queue and sessions are on the database.

## Decision

1. **Ansible runs in a container**, like every other tool (ADR-0007): an `ansible` service of `compose.yaml` under a profile, built from `python:3-alpine` with `ansible-core` and the collections pinned in `ansible/`. CI (E1.3c) installs the same pins on the runner. Nothing to install on the host but Docker.
2. **Every package comes from the Ubuntu 26.04 archive**, with the versions of `versions.yaml` (E1.3a); no third-party repository. Redis moves to 8 in `versions.yaml` and `compose.yaml` to match the archive, and is installed on the server only when a story uses it (Reverb, E4, or a Redis cache): no idle service (RGESN).
3. **Releases side by side:** `/var/www/balise/releases/<version>`, `shared/.env` and `shared/storage` linked into each, `current` pointing at the live one; the last three releases are kept, so a rollback is one symlink switch. nginx serves `current/public`.
4. **One system user `balise`** owns the releases and runs the php-fpm pool (Unix socket) and two systemd units, `balise-queue` and `balise-scheduler`, with the commands of `compose.yaml` (`queue:work --tries=3`, `schedule:work`). Ansible connects as the machine's admin user (`ubuntu`) with sudo, over SSH with a key.
5. **The repository's files are installed as they are:** `docker/nginx/site.conf` (Ansible adds `server_name`), `docker/php/php.ini`, a shared `docker/php/fpm-pool.conf` and `docker/php/packages.txt`; only server concerns are written in `ansible/` (users, firewall, unattended upgrades, systemd units, `.env` template).
6. **Secrets are variables, never in the repository:** `APP_KEY` and the database password are passed to the playbook (environment variables, from GitHub secrets in E1.3c, from an untracked inventory file locally) and written to `shared/.env`. No Ansible Vault.
7. **A build is an archive made outside the server** by `scripts/build-release.sh` in the `test` image (Composer without dev packages, Vite build, GTFS-Realtime classes generated): the server needs no Node, Composer or Git (ADR-0004).
8. **Proof on a local machine:** an OrbStack Ubuntu 26.04 arm64 machine (the architecture of the Oracle A1 instance), set up by the cloud-init file Oracle will receive, so the same first boot is tested. Any Ubuntu 26.04 host reachable by SSH works (Multipass, UTM, a VPS).

## Alternatives considered

| Option | Why not |
|---|---|
| Ansible installed on the host (Homebrew, pipx) | One more tool and version per contributor; drifts from CI |
| Ansible from the Ubuntu archive in a `ubuntu:26.04` image | Ties the Ansible version to the distribution; CI on the runner would install another one |
| Redis 7 from the redis.io repository | A third-party repository for a service the application does not use yet |
| Ansible Vault for secrets | One more secret (the vault password) to keep, for two values GitHub secrets already hold |
| Supervisor for the workers | systemd is already there and restarts units; one less package |
| Cron `schedule:run` every minute | Differs from `compose.yaml` (`schedule:work`), and ADR-0007 wants identical daemon commands |
| A Vagrant or Multipass virtual machine | Slower to boot; OrbStack already runs the Docker engine here; the playbook does not depend on it |
| Ansible Galaxy roles (geerlingguy.php, nginx…) | Hide what is installed behind many variables; the configuration is small enough to read in a few tasks |

## Consequences

- A contributor proves the server with Docker and any Ubuntu 26.04 machine; the same playbook runs from CI against Oracle in E1.3c.
- The OrbStack machine shares the Mac's kernel: kernel-level settings (sysctl, firewall backends) are only proven on a real virtual machine, in E1.3c.
- Redis 8 replaces 7 locally; the application is not affected, since it does not use Redis yet.
- Three releases on disk, each with its `vendor`: about 3 × 80 MB.
