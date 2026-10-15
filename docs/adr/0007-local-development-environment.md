# ADR-0007: Local development environment close to Forge

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

- Production runs on Ubuntu 24.04 provisioned by Laravel Forge: PHP from `ppa:ondrej/php`, nginx + php-fpm, supervisor daemons for workers, a cron entry for the scheduler. No Docker in production.
- The application needs PHP 8.5, PostgreSQL (PostGIS later, ADR-0002), Redis, a queue worker, the scheduler, Reverb and Vite.
- A reviewer must be able to start the project with one command, on macOS or Linux.
- Laravel Sail is also Ubuntu 24.04 with the same PPA, but serves the app with `php artisan serve`, ships a large toolset, and leaves workers to be started by hand.

## Decision

1. **A hand-written `compose.yaml`, one process per service:** nginx, php-fpm, queue worker, scheduler (`schedule:work`), Reverb, Vite, PostgreSQL, Redis. `docker compose up` starts everything.
2. **PHP and nginx images are built from `ubuntu:24.04`**, with PHP 8.5 from `ppa:ondrej/php`: same distribution, packages and versions as Forge. This is the specific constraint that overrides the usual preference for Alpine.
3. **PostgreSQL and Redis use the official Alpine images**, pinned to the major versions installed by Forge. Only the major version matters for them.
4. **The repository is the source of truth for application-facing configuration:**
   - `docker/nginx/site.conf`, `docker/php/php.ini`, `docker/php/fpm-pool.conf` and the list of `php8.5-*` packages;
   - the same files are applied to the Forge site (nginx and PHP editors, or the Forge API if it allows it);
   - worker commands are identical to the Forge daemons.
5. **Host concerns are not reproduced:** SSL, firewall, SSH, users, unattended upgrades, systemd.
6. **This environment is for development only.** No production image is built from it.

## Alternatives considered

| Option | Why not |
|---|---|
| Laravel Sail | Same base, but `artisan serve` instead of nginx + php-fpm, large image, workers started by hand, `composer install` needed before the first start |
| `compose.yaml` on Alpine | Lighter, but musl instead of glibc and different package names: further from production |
| Laravel Herd | macOS and Windows only; PostgreSQL, Redis and Reverb require Herd Pro; nothing versioned |
| Native Homebrew | Not reproducible for a reviewer |
| DDEV | One more tool to install, uncommon in the Laravel ecosystem, hides the Docker setup |
| One container mimicking the whole server (systemd, supervisor, cron) | Defeats the purpose of containers; separate services give the same fidelity |

## Consequences

- Timeouts, upload limits and the WebSocket proxy for Reverb behave locally as in production.
- The image and its configuration are maintained by the project: PHP updates and security fixes are our job.
- CI runs natively on `ubuntu-latest`, a third check of the same platform.
- Once the server exists (E1.3), its actual configuration files are compared with the repository and any gap is fixed. The comparison is repeated after Forge actions that rewrite configuration, such as enabling SSL.
