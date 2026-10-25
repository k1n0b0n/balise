# balise — agent instructions

## Project

Live map of public transport vehicles fed by open GTFS-Realtime data. First network: TBM (Bordeaux). Audience: technical reviewers reading the repo in about ten minutes, so readability beats cleverness.

Stack: Laravel 13, Livewire 4, PHP 8.5, PostgreSQL 18. Tests: Pest (feature tests in `tests/Feature`, architecture tests in `tests/Unit/ArchitectureTest.php`), run against the `testing` PostgreSQL database.

## Commands

Everything runs in Docker (ADR-0007); no PHP is needed on the host.

| Task | Command |
|---|---|
| Start the stack (first run installs dependencies, creates `.env`, migrates) | `docker compose up -d` |
| Open the app | http://localhost:8000 (`APP_PORT` to change) |
| All quality gates: Pint, Larastan, Pest with browser tests (built assets needed, see below) | `docker compose run --rm test` |
| Same, for an AI agent: compact JSON output through `laravel/pao` | `docker compose run --rm -e CLAUDECODE -e AI_AGENT test` |
| Fast tests without a browser | `docker compose exec php vendor/bin/pest --testsuite=Unit,Feature` |
| Fix code style | `docker compose exec php vendor/bin/pint` |
| Browser tests (axe) need built assets first | `docker compose exec vite npm run build` |
| Type-check the front end / check the map page JavaScript budget | `docker compose exec vite npm run typecheck` / `docker compose exec vite npm run budget` |
| One test file / one test | `docker compose exec php vendor/bin/pest tests/Feature/HomeTest.php` / `--filter "renders the home page"` |
| Artisan | `docker compose exec php php artisan <command>` |
| Import a network's lines and stops (static GTFS) | `docker compose exec php php artisan network:import tbm` |
| Read a network's live vehicle positions once (the scheduler does it every 20 s) | `docker compose exec php php artisan network:poll tbm` |
| Archive a network's finished service days of recordings (the scheduler does it each night when `RECORDINGS_ENABLED=true`; ADR-0022) | `docker compose exec php php artisan network:archive tbm` |
| Regenerate the GTFS-Realtime classes in `generated/` (not committed, built by setup and CI; never edit them) | `docker compose exec php sh scripts/generate-gtfs-realtime.sh` |
| Stop / reset the database | `docker compose down` / `docker compose down -v` |
| Create a local stand-in for a host (on the host; ADR-0020 point 9): fresh Debian 13, fresh Ubuntu 26.04, or Debian 13 with another site / remove it | `sh scripts/local-server.sh debian\|ubuntu\|shared` / `orb delete -f balise-debian` |
| Prepare the local machines / a real host from its ignored inventory, through your SSH agent, as Docker Desktop and similar tools expose it (`--check --diff` to see drift) | `docker compose run --rm ansible ansible-playbook host.yml` / `docker compose run --rm -v /run/host-services/ssh-auth.sock:/run/host-services/ssh-auth.sock -e SSH_AUTH_SOCK=/run/host-services/ssh-auth.sock ansible ansible-playbook -i local/inventory.yml host.yml` |
| See which of PHP, PostgreSQL, Redis and Node has a newer version / rewrite the places that name one (the weekly `versions` workflow opens the pull requests) | `php scripts/raise-versions.php` / `php scripts/raise-versions.php apply postgresql 19` (on the host, or through `docker compose exec php`) |
| Release a version | Merge the pull request `chore: release x.y.z` that release-please keeps open on `main`: it tags `vx.y.z` and publishes the GitHub release |
| Build the production images locally (`app`, then `web` from it) | `docker build -f docker/php/Dockerfile --target production --build-arg POSTGRESQL_VERSION=18 --build-arg REDIS_VERSION=8 -t balise-php:production .` / `docker build -f docker/nginx/Dockerfile --target production -t balise-nginx:production .` |
| Deploy a release on a prepared host / go back to the previous one | `ssh balise-deploy@<host> v1.2.3` / `ssh balise-deploy@<host> rollback` |

Agents: always forward `-e CLAUDECODE -e AI_AGENT` to `docker compose exec` or `docker compose run` when running Pint, Larastan or Pest. The variables are copied only when set in the calling shell, so humans keep the normal output; never put them in `compose.yaml`, where they would be frozen at container creation.

Laravel Boost (ADR-0009) appends generated guidelines at the end of this file; never edit between its tags, run `docker compose exec php php artisan boost:update` instead. Its commands assume PHP on the host: run them through `docker compose exec php`. Where they conflict with this file or an ADR, this file wins. The Boost MCP server (`.mcp.json`) runs in the PHP container; its log tools see nothing, use `docker compose logs php`.

The feed-format boundary rule (`tests/Unit/ArchitectureTest.php`) forbids `Google\Protobuf` and the generated `Transit_realtime` classes outside `App\Ingestion`; it was proven with a deliberate violation in E2.2.

## Rules

- Start every session by reading the *Current state* section of `docs/README.md`, and update it before ending.
- Follow the BMAD-lite workflow in `docs/README.md`. Work on one story at a time, from its file in `docs/stories/` (copy `_template.md`). Studies of possible features go to `docs/ideas/`; an idea never justifies code until the PRD has its story.
- Never implement anything that is not in `docs/prd.md`. Propose it for the *Later* section instead.
- A decision that is costly to reverse (new dependency, boundary, data store, hosting) needs an ADR in `docs/adr/` before code.
- All code, docs, comments and commit messages in English. Conventional Commits.
- Open code pull requests as drafts (`gh pr create --draft`). The maintainer reviews first and marks them ready (or asks for `gh pr ready`), which starts CodeRabbit's one review of the whole pull request on its own; never comment `@coderabbitai full review` unless the maintainer asks (ADR-0014). A story's documentation (story file, its closing as *Done*, *Current state*) goes in the story's code pull request, even when a check can only run after the merge: write that check as a follow-up in *Current state*, never a pull request to close the story afterwards; a separate `docs:` pull request, one per session, opened ready for review without the bot, is only for planning work without code (ADR, PRD, ideas) or a session that ends without a code pull request. A pull request that changes only `docs/` skips CI's jobs; the required `ci` check reports green.
- A story is done only when tests, static analysis and architecture tests pass, the non-functional requirements of the PRD (RGAA, RGESN) are met, and the diff has been reviewed by a human.

## Architecture (from `docs/architecture.md` and the ADRs)

- **Ingestion boundary (ADR-0001).** Two app-owned ports: `VehicleFeed` (live positions) and `NetworkCatalog` (lines, stops, shapes). One adapter per *standard*, not per network: `gtfs-rt`, `gtfs`, `replay` for the MVP; `siri-vm`, `netex` only when a network needs them. Networks pick drivers through configuration (URLs, timezone, id prefix).
- **Only the ingestion layer knows about GTFS, GTFS-RT, SIRI, protobuf or any feed format.** Architecture tests enforce it. Everything outside is plain Laravel; no hexagonal/DDD ceremony elsewhere.
- **Normalised model** (network, line, stop, vehicle, position): GTFS-inspired but owned by the app, ids namespaced per network, fields that some standards lack are optional. Never mirror GTFS tables as-is. A position (ADR-0013) requires vehicle id, coordinates and `recordedAt`, each with a documented fallback; `lineId` and `tripId` are optional, and a vehicle without a line is shown ("?" on the map, "Ligne inconnue" in text), never hidden. Positions more than 5 min old or 2 min in the future are dropped.
- **Single write path (ADR-0002).** Every position write goes through `App\Actions\RecordPositions`, which emits `PositionsUpdated` with the changed positions and the removed vehicles. Its listener today is the change log behind the positions endpoint; history and future features will listen to it too.
- **Storage (ADR-0002, Accepted).** PostgreSQL; coordinates as plain `latitude`/`longitude` until the first spatial story brings PostGIS (own ADR, migration to `geography(Point, 4326)`). M1 keeps only the latest position per vehicle; latest positions are hot data for Redis/memory, the DB is the system of record. Local dev uses a PostgreSQL Alpine image.
- **Replay** records raw feed payloads and plays them back through the real driver; it does not depend on position history.
- **UI (ADR-0010).** Livewire + Alpine + MapLibre GL JS. The map and the live store, which asks the positions endpoint for what changed since its version (`?since=`, `ETag` and `304`), are a TypeScript module in `resources/js/map/` that never imports Livewire or Alpine; Livewire mounts it with `wire:ignore` and talks to it through events. RGAA AA and RGESN apply (ADR-0006): the map has an equivalent text view and a pause control, and only changed positions are sent. Pushing them (Reverb or SSE) is not built: E4 is being replanned around this polling.
- **Local environment (ADR-0007).** `compose.yaml`, one process per service; PHP and nginx images from the official Alpine images, the base of the production images (ADR-0020 point 2). The PHP Dockerfile has three targets: `runtime` (queue, scheduler; the base of the production images), `dev` (php, setup: adds Composer and tools) and `test` (PHP on Debian with Composer, Node and Chromium, since Playwright needs glibc; used only by the on-demand `test` service and CI's browser tests). The extensions are built by `docker/php/install-extensions.sh` for both. Application-facing config (nginx, `php.ini`, php-fpm pool) is versioned under `docker/` and built into the images.
- **Hosting (ADR-0020).** Docker images `app` and `web` from GHCR on one Debian 13 or Ubuntu 26.04 server, prepared once by an Ansible bootstrap that only a person runs; the host's nginx proxies to them. Deploys run from GitHub Actions on release through an SSH key restricted to the deploy command (ADR-0004). The production server's name, address, port and domain never appear in the repository: they live in GitHub secrets and variables and in an ignored local inventory.

## Data facts

- TBM feed: ~424 vehicles at midday; some positions are up to ~3.5 h old and must be filtered (E2.3). The network is nearly empty at night, hence replay mode.
- Source: transport.data.gouv.fr, Licence Ouverte.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
