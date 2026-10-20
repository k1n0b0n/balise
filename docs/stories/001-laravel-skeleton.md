# Story 001: Laravel + Livewire skeleton with quality gates

- **PRD:** E1.1
- **Status:** Done (merged in #4; deployment comes with E1.3)

## Acceptance criteria

See E1.1 in [prd.md](../prd.md).

## Tasks

- [x] PHP and nginx images on Ubuntu 24.04, PHP 8.5 from `ppa:ondrej/php` (ADR-0007)
- [x] `compose.yaml`: nginx, php-fpm, queue, scheduler, Vite, PostgreSQL, Redis
- [x] Laravel skeleton, Livewire, PostgreSQL and Redis configuration
- [x] Livewire home page with a feature test
- [x] Pint, Larastan level max, Pest with architecture tests, `composer qa` (ADR-0005)
- [x] README and AGENTS.md commands

## Notes

- **Versions installed:** Laravel 13.36, Livewire 4.4, Pest 5.3 (PHPUnit 13), Larastan 3.13 (PHPStan 2.3), PHP 8.5.11. ADR-0005 mentioned Pest 4; it now says "browser testing plugin".
- **Pint** adds `declare(strict_types=1)` to every PHP file (`declare_strict_types` rule), so the strict types architecture rule is fixed automatically.
- **The feed-format boundary rule passes vacuously for now:** Pest architecture expectations ignore classes that cannot be autoloaded (checked with a probe class using `Google\Protobuf`). The rule bites once E2.2 installs the protobuf/GTFS-RT packages; E2.2 should prove it with a deliberate violation.
- **Redis** runs in the stack (ADR-0007) but queue, cache and session keep Laravel's `database` drivers: Redis is still "to confirm" in `architecture.md`.
- **To check against the server in E1.3:** PostgreSQL 17 and Redis 7 are guesses of what the server will run.
- **Docker-only configuration** is kept apart from the shared files: `docker/php/fpm-docker.conf` (TCP listen, env passing, logs to stderr) and `docker/nginx/upstream-docker.conf`. There is no shared php-fpm pool setting yet, so ADR-0007's `fpm-pool.conf` does not exist.
- **php-fpm runs as `ubuntu`** in Docker so that files created through `docker compose exec` belong to the host user (uid 1000 on Linux).
- **The generated skeleton was pruned to the PRD** after review: no `User` model, factory or `users`/`password_reset_tokens` tables (accounts are out of scope, brief); only the `sessions` table is kept, for Livewire's CSRF protection. Tailwind and the Bunny web font are removed (new dependency without ADR, extra page weight against RGESN), as are `laravel/pail`, `laravel/pao`, `concurrently`, `@laravel/multiplex`, the `inspire` command and the `setup`/`dev` composer scripts. Lesson: prune a generated skeleton against the PRD before the first commit.
- **Tooling decisions after the pruning:** Tailwind gets ADR-0008 (installed with E3.1), the web font and `laravel/pail` go to PRD *Later*, `concurrently` and `@laravel/multiplex` are rejected in ADR-0007, amended to send logs to stderr in Docker (`decorate_workers_output = no` removes php-fpm's per-line prefix).
- **`laravel/pao` added back, for agents only:** checked with deliberate failures that the JSON keeps file, line and message for Pint, Larastan and Pest, and that output is unchanged without `-e CLAUDECODE -e AI_AGENT`. PHPStan's JSON also carries instructions for agents (no baseline, no ignore comments), consistent with ADR-0005.
- **Nested `.gitignore` files** of the skeleton were lost when merging it into the repository, so compiled views and package caches were committed by mistake; they are restored and the caches untracked.
- **nginx restarts with php** (`depends_on.restart`): nginx resolves the upstream once, and a recreated php container gets a new IP.
