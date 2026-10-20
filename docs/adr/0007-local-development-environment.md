# ADR-0007: Local development environment close to production

- **Status:** Accepted
- **Date:** 2026-10-15
- **Amended:** 2026-10-15, during E1.1: logs on stderr, no process runner, `laravel/pao` for agents
- **Amended:** 2026-10-16: Ubuntu 26.04, Forge's default for new servers since 2026-08-31; PHP from the Ubuntu archive
- **Amended:** 2026-10-16: hosting moved off Forge to an Oracle Cloud server provisioned with OpenTofu and Ansible (ADR-0012); the server is configured from the same files
- **Amended:** 2026-10-16, during E3.1: the PHP Dockerfile has three targets, `runtime` (353 MB, queue and scheduler), `dev` (461 MB, adds Composer and tools) and `test` (2.3 GB, adds Node and a Chromium pinned to the Playwright version of `package.json`, built only for the on-demand `test` service)
- **Amended:** 2026-10-21: production runs Docker images built from `runtime` (ADR-0020); point 6 is decided

## Context

- Production runs on Ubuntu 26.04, configured by Ansible (ADR-0012): nginx + php-fpm, systemd or supervisor daemons for workers, the scheduler. No Docker in production.
- The application needs PHP 8.5, PostgreSQL (PostGIS later, ADR-0002), Redis, a queue worker, the scheduler, Reverb and Vite.
- A reviewer must be able to start the project with one command, on macOS or Linux.
- Laravel Sail runs Ubuntu 24.04 with `ppa:ondrej/php`, serves the app with `php artisan serve`, ships a large toolset, and leaves workers to be started by hand.

## Decision

1. **A hand-written `compose.yaml`, one process per service:** nginx, php-fpm, queue worker, scheduler (`schedule:work`), Reverb, Vite, PostgreSQL, Redis. `docker compose up` starts everything.
2. **PHP and nginx images are built from `ubuntu:26.04`**, with PHP 8.5 from the Ubuntu archive: same distribution and packages as the server, which installs them the same way. `ppa:ondrej/php` has no 26.04 build. This is the specific constraint that overrides the usual preference for Alpine.
3. **PostgreSQL and Redis use the official Alpine images**, pinned to the major versions installed on the server. Only the major version matters for them.
4. **The repository is the source of truth for application-facing configuration:**
   - `docker/nginx/site.conf`, `docker/php/php.ini`, `docker/php/fpm-pool.conf` and the list of `php8.5-*` packages;
   - Ansible installs the same files on the server;
   - worker commands are identical to the server's daemons.
5. **Host concerns are not reproduced:** SSL, firewall, SSH, users, unattended upgrades, systemd.
6. **Production images start from `runtime`** (ADR-0020): a `production` target adds the built application; `dev` and `test` stay for development.
7. **Logs go to stderr in Docker** (`LOG_CHANNEL=stderr` in `compose.yaml`), so `docker compose logs` shows the application logs of every PHP process. The server keeps Laravel's file logs.
8. **No process runner** (`concurrently`, `@laravel/multiplex`, `composer dev`): compose already runs one process per service.
9. **`laravel/pao` gives AI agents compact JSON output** from Pint, Larastan and Pest. Agents forward their detection variables per command (`docker compose exec -e CLAUDECODE -e AI_AGENT …`); they are never set in `compose.yaml`, where they would be frozen at container creation and switch the output for humans too.

## Alternatives considered

| Option | Why not |
|---|---|
| Laravel Sail | Same base, but `artisan serve` instead of nginx + php-fpm, large image, workers started by hand, `composer install` needed before the first start |
| `compose.yaml` on Alpine | Lighter, but musl instead of glibc and different package names: further from production |
| Laravel Herd | macOS and Windows only; PostgreSQL, Redis and Reverb require Herd Pro; nothing versioned |
| Native Homebrew | Not reproducible for a reviewer |
| DDEV | One more tool to install, uncommon in the Laravel ecosystem, hides the Docker setup |
| One container mimicking the whole server (systemd, supervisor, cron) | Defeats the purpose of containers; separate services give the same fidelity |
| `concurrently` or `@laravel/multiplex` behind `composer dev` | Duplicates compose; a second way to start the app for contributors without Docker would rot unnoticed |
| `laravel/pail` to read logs | stderr + `docker compose logs` covers the need for now; reconsidered when ingestion produces logs worth filtering (PRD *Later*) |

## Consequences

- Timeouts, upload limits and the WebSocket proxy for Reverb behave locally as in production.
- The image and its configuration are maintained by the project: PHP updates and security fixes are our job.
- CI runs natively on `ubuntu-latest`, a third check of the same platform.
- The server is configured from these files by Ansible, so parity holds by construction; a weekly check-mode run reports drift (E1.3).
