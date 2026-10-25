# ADR-0019: Server configuration and deploys with Ansible

- **Status:** Accepted
- **Amended:** 2026-10-21 by ADR-0020: the application runs in Docker images on the host; points 1, 2 (for the images) and 6 stay, points 3, 4, 5, 7 and the playbooks `server.yml`, `deploy.yml` and `rollback.yml` give way in E1.3d
- **Date:** 2026-10-20

## Context

ADR-0012 configures the server with Ansible from the repository, and ADR-0004 deploys a build with an Ansible playbook from GitHub Actions. E1.3b writes both playbooks and proves them on a local Ubuntu 26.04 machine before any cloud account exists (E1.3c). Several choices are costly to reverse: how Ansible is run, where packages come from, how the application is laid out and run on the server, and where secrets live.

Measured on 2026-10-20 in `ubuntu:26.04`: the archive has nginx 1.28, PHP 8.5.4, PostgreSQL 18 and Redis 8.0 (not 7, the version of `versions.yaml` and `compose.yaml`). The application uses no Redis yet: cache, queue and sessions are on the database.

## Decision

1. **Ansible runs in a container**, like every other tool (ADR-0007): an `ansible` service of `compose.yaml` under a profile, built from `python:3-alpine` with `ansible-core` and the collections pinned in `ansible/`. CI (E1.3c) installs the same pins on the runner. Nothing to install on the host but Docker.
2. **Every package comes from the Ubuntu 26.04 archive**, with the versions of `versions.yaml` (E1.3a); no third-party repository. Redis moves to 8 in `versions.yaml` and `compose.yaml` to match the archive, and is installed on the server now, though the application does not use it yet: about 10 MB of memory keeps the server's services the same as `compose.yaml`, and Reverb (E4) adds nothing to install.
3. **Releases side by side:** `/srv/balise/releases/<build>`, with `shared/.env` and `shared/storage` linked into each; `/var/www/balise`, the path of `compose.yaml` and of `site.conf`'s root, is the link to the live release. The last three releases are kept, so a rollback is one link switch (`rollback.yml`).
4. **One system user `balise`** owns the releases and runs the php-fpm pool (Unix socket) and two systemd units, `balise-queue` and `balise-scheduler`, with the commands of `compose.yaml` (`queue:work --tries=3`, `schedule:work`). Queues moved to Redis would keep this: `queue:work` only changes connection, and Horizon is one process under systemd that runs its own workers; that story would set Redis persistence, since pending jobs would then live there. Ansible connects as the machine's admin user (`ubuntu`) with sudo, over SSH with a key.
5. **The repository's files are installed as they are:** `docker/nginx/site.conf` (the only site; its domain comes with E1.4), `docker/php/php.ini` and `docker/php/packages.txt`. Only server concerns are written in `ansible/`: the user, the upstream on php-fpm's socket, the pool's user, systemd units, the `.env` template, firewall, unattended upgrades. There is still no shared pool setting, so no `fpm-pool.conf` (story 001).
6. **Secrets local to the server are generated on it:** the server playbook generates `APP_KEY` and the database password once, into root-only files of `shared/secrets/`, only when those files are missing, and writes `shared/.env` from them on every run; they never leave the machine. A rebuild gets new ones, which only ends open sessions: the application encrypts no stored data, and a rebuild reloads its data anyway (ADR-0012). Secrets that cross machines (deploy SSH key, OCI, Cloudflare) are GitHub secrets (E1.3c), passed as variables.
7. **A build is an archive made outside the server** by `scripts/build-release.sh` in the `test` image (Composer without dev packages, Vite build, GTFS-Realtime classes generated): the server needs no Node, Composer or Git (ADR-0004).
8. **Proof on a local machine:** a local Ubuntu 26.04 arm64 machine (the architecture of the Oracle A1 instance), created by `scripts/local-server.sh` with a cloud-init that adds what Oracle's image already has (an SSH server, the admin user's key). Any Ubuntu 26.04 host reachable by SSH works (Multipass, UTM, a VPS).

## Alternatives considered

| Option | Why not |
|---|---|
| Ansible installed on the host (Homebrew, pipx) | One more tool and version per contributor; drifts from CI |
| Ansible from the Ubuntu archive in a `ubuntu:26.04` image | Ties the Ansible version to the distribution; CI on the runner would install another one |
| Redis 7 from the redis.io repository | A third-party repository, for a version the archive has replaced |
| Redis installed only when a story uses it | Nothing idle, but the server would differ from `compose.yaml` until then |
| Ansible Vault for secrets | The repository is public: the encrypted file can be attacked offline without limit, and a leak stays in the history for ever; the vault password is one more secret; rotating means re-encrypting and committing |
| Every secret as a GitHub secret, passed to the playbook | The database password and `APP_KEY` would leave the only machine that uses them, and need a copy outside Git for local runs |
| Supervisor for the workers | Familiar to Laravel readers (Laravel's documentation), but a second supervisor started by systemd, with its own logs; systemd already restarts units, orders them after PostgreSQL, limits their memory and sandboxes them |
| Cron `schedule:run` every minute | Differs from `compose.yaml` (`schedule:work`), and ADR-0007 wants identical daemon commands |
| A Vagrant or Multipass virtual machine | Slower to boot; the tool that runs the local machines already runs Docker on the workstation; the playbook does not depend on it |
| Ansible Galaxy roles (geerlingguy.php, nginx…) | Hide what is installed behind many variables; the configuration is small enough to read in a few tasks |

## Consequences

- A contributor proves the server with Docker and any Ubuntu 26.04 machine; the same playbook runs from CI against Oracle in E1.3c.
- The local machine shares the workstation's kernel: kernel-level settings (sysctl, firewall backends) are only proven on a real virtual machine, in E1.3c.
- Redis 8 replaces 7 locally and runs on the server; the application is not affected, since it does not use Redis yet.
- Three releases on disk, each with its `vendor`: about 3 × 80 MB.
