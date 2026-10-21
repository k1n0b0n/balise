# Story 017: Docker deploy on any Debian or Ubuntu host, proven on local machines

- **PRD:** E1.3d
- **Status:** In progress

## Acceptance criteria

See E1.3d in [prd.md](../prd.md). Decisions in [ADR-0020](../adr/0020-docker-hosting.md).

## Tasks

- [x] Official base images: `runtime` and `dev` from `php:8.5-fpm-alpine`, `test` from `php:8.5-cli-trixie`, the missing extensions built by one script, `docker/nginx/Dockerfile` from `nginx:alpine`; the local stack starts and every quality gate passes on them
- [x] CI in the images: one job per image (`dev`, `test`), then style, static analysis, tests, front end and audit run on `dev` or the vite service's Node, the browser tests on `test` after the front-end build; a final job `ci` needs them all; CI's duration before (2 min 31 s on #57) and after recorded
- [x] Each version in one place: PHP and Node in the Dockerfile, PostgreSQL and Redis in `compose.yaml`; `versions.yaml`, `scripts/check-versions.php`, its test and the `versions` job removed (`docker/php/packages.txt` too, replaced by the Dockerfile's extension list); the maintainer drops `versions` from the ruleset's required checks
- [x] Production images: a `production` target (code, Composer without dev packages, built assets, generated classes, `optimize` at start) and the `web` image with `public/`; PostgreSQL's and Redis's versions as labels of `app`
- [ ] Bootstrap `ansible/host.yml` with its templates: Docker, nginx and certbot from the archive when missing, one nginx site and its certificate, the deploy user restricted to the deploy command, `/srv/balise` with the production `compose.yaml`, generated secrets and the deploy script; firewall and unattended upgrades optional; an example inventory, the real one ignored by Git
- [ ] Deploy script: version or `rollback` only, pull, migrations with the new image, restart, `network:import`, health check with automatic return, vhost rendered from the host's template, versions file from the labels, PostgreSQL major moved by dump and restore, three versions kept
- [ ] Release workflow: on `release: published`, build and push both images, then deploy over SSH from the `production` environment, which waits for the maintainer's approval
- [ ] E1.3b's host install removed: `server.yml`, `deploy.yml`, `rollback.yml`, their templates and `scripts/build-release.sh`
- [ ] `docs/deploy.md`: from a fork to a deployed release
- [ ] Proof on three OrbStack machines (fresh Debian 13, fresh Ubuntu 26.04, Debian 13 already running Docker and nginx with another site): bootstrap twice (`changed=0` the second time), two deploys, a rollback, a PostgreSQL move from 17 to 18; the other site and Docker left untouched
- [ ] Closing: story notes, *Current state*, ideas review (step 5)

## Notes

- **Image sizes** (uncompressed, arm64), first on `php:8.5-fpm-trixie` for every target: `runtime` 773 MB instead of 353 MB on Ubuntu, `dev` 837 MB, `test` 2.5 GB. The Debian image keeps its compilers (721 MB alone), and Playwright installed the full Chromium beside its headless shell (400 MB), Mesa and LLVM for a libgbm the shell only links (180 MB) and fonts for every script (87 MB). After moving `runtime` and `dev` to Alpine (ADR-0020 point 2 amended) and slimming `test`: `runtime` 176 MB (50 MB compressed), `dev` 194 MB, `test` 1.04 GB (268 MB compressed), `web` 102 MB.
- **`test` is flattened:** built in a `test-build` stage that purges the compilers, then copied into `FROM scratch`, so the base image's layers do not keep them; its `ENV` is declared again. Mesa goes with `dpkg --force-depends`, and the map's WebGL tests pass on the shell's SwiftShader.
- **busybox's `sha256sum` has no `--quiet`:** the GTFS-Realtime script checks protoc with `-c -` and discards the output; the protoc release runs on musl.
- **phpredis from GitHub, not PECL:** `pecl install` failed on a 504 from pecl.php.net during the first build; the release archive is fetched from GitHub and checked by SHA-256, then built with `docker-php-ext-install` like the bundled extensions.
- **`sockets` is needed:** Ubuntu's PHP had it built in; `pestphp/pest-plugin-browser` requires it, so the Dockerfile builds it.
- **nginx's official image compresses nothing** (`#gzip on;` in its `nginx.conf`): `site.conf` now turns gzip on itself.
- **CI in the image, measured:** 4 min 12 s from push to the final `ci` job, against 2 min 31 s for the single `setup-php` job on #57. Each job spends about a minute loading the 2.5 GB image from the cache and installing Composer packages; the image job takes 15 to 20 s once its cache is warm (4.5 min cold). Pint fails within about 80 s instead of after the whole run. Feature tests render pages, which need the Vite manifest, so they run after the front-end build, beside the browser tests.
- **Gotcha in GitHub expressions:** `cond && '' || 'x'` always gives `'x'`, since `''` is falsy; the run action tests `database != 'true'` instead.
- **Production images, measured:** `app` 246 MB (the runtime's 176 MB plus 55 MB of `vendor` without dev packages), `web` 104 MB. Smoke test against the local PostgreSQL: `/up` 200, the page and a hashed asset served by `web`, `optimize`'s caches built at start, the code read-only for `balise`. The build refuses to run without `POSTGRESQL_VERSION` and `REDIS_VERSION` as majors.
- **`web` copies `public/` from the `app` image of the same release** (`APP_IMAGE` build argument), so both serve the same built assets; the `dev` target of `docker/nginx/Dockerfile` never pulls it. A `.dockerignore` keeps `.env`, `vendor`, `node_modules` and local state out of every build context.
