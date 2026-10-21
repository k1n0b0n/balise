# ADR-0020: Hosting with Docker on a server prepared by Ansible

- **Status:** Accepted
- **Date:** 2026-10-21

## Context

- The Oracle Cloud account of ADR-0012 could not be created (sign-up failed after the identity check, 2026-10-16), and the maintainer does not want to pay for hosting.
- The maintainer already owns a dedicated server that runs other services. It has Docker and an nginx in front of them, on a distribution other than Ubuntu 26.04. The repository is public: the server's name, address, port and services stay out of it.
- E1.3b installs PHP 8.5 and PostgreSQL 18 on the host from the Ubuntu 26.04 archive (ADR-0019). A Debian 13 host has neither (PHP 8.4, PostgreSQL 17), and installing them would touch a machine that serves other things.
- The local images run Ubuntu 26.04 with the archive's PHP (ADR-0007), to match a server installed natively. With the image as the server, that reason is gone, and the archive keeps PHP at 8.5.4 until Ubuntu 28.04 (April 2028), whereas the official `php:8.5-fpm-trixie` image had 8.5.11 on 2026-10-21. Point 6 of ADR-0007 leaves Docker in production to its own ADR, with a `production` target built from `runtime`.
- Anyone should be able to deploy the project from the code onto a machine of their own, with templates and a documented process.
- Measured on 2026-10-21 in the archives: Debian 13 has `docker.io` 26.1, `docker-compose` 2.26, nginx 1.26 and certbot 4.0; Ubuntu 26.04 has `docker.io` 29.1, `docker-compose-v2` 2.40, nginx 1.28 and certbot 4.0.

## Decision

1. **The host is any Debian 13 or Ubuntu 26.04 machine reachable by SSH**, freshly installed or already running other services. The demo runs on the maintainer's server; its name, address, SSH port and domain live in GitHub secrets and variables and in a local inventory ignored by Git, never in the repository.
2. **Official base images instead of Ubuntu** (ADR-0007 amended), as small as each use allows (amended in E1.3d, when the first images measured `runtime` 773 MB and `test` 2.5 GB):
   - `runtime`, and `dev` built on it, from `php:8.5-fpm-alpine`, with the extensions the image lacks (bcmath, intl, pdo_pgsql, pgsql, redis, sockets, zip) built by one script, `docker/php/install-extensions.sh`. Production, the local stack, style, static analysis and the unit and feature tests run on it.
   - `test`, for the browser tests only, from `php:8.5-cli-trixie` with the same script, Node and Chromium's headless shell, flattened into one layer once the compilers are gone. Playwright's browsers need glibc, and Pest starts Playwright and the application in its own container, so the browser cannot live in an image of its own. The PHP of the browser tests thus runs on glibc and production's on musl; the unit and feature tests cover production's libc.
   - `web` from `nginx:alpine`.
   PHP, PostgreSQL, Redis and Node each move alone, by their image tags.
3. **Each version is written in one place, so `versions.yaml` and `scripts/check-versions.php` go.** CI runs the quality gates in the `test` image instead of installing PHP with `setup-php`, so it also proves the Dockerfile: PHP and Node are named only in `docker/php/Dockerfile`, PostgreSQL and Redis only in `compose.yaml` (the Vite service's Node excepted, until it builds from the same Dockerfile). The release build reads PostgreSQL's and Redis's versions from `compose.yaml` and stores them as labels of the `app` image (`balise.postgresql`, `balise.redis`); the deploy reads the labels of the image it pulled, accepts digits only, and writes them to the host's versions file that the production `compose.yaml` interpolates. The cost is a slower CI, measured in E1.3d against the `setup-php` job.
4. **Two production images, built on release** by GitHub Actions for `linux/amd64` and pushed, public, to the GitHub Container Registry of the repository's owner (`ghcr.io/<owner>/balise-app`, `…/balise-web`), tagged with the release version:
   - `app`: a `production` target of `docker/php/Dockerfile` built from `runtime`, with what `scripts/build-release.sh` puts in the archive (code, Composer without dev packages, built assets, generated GTFS-Realtime classes). php-fpm, the queue worker, the scheduler and one-off commands run it; each container runs `php artisan optimize` at start, since `.env` only exists on the host.
   - `web`: the nginx image of `docker/nginx/` with `public/`.
5. **One bootstrap playbook, `ansible/host.yml`, run by a person with root** from the `ansible` service (ADR-0019 point 1). It installs, from the templates in `ansible/`:
   - Docker and its compose plugin from the distribution's archive when missing; an installed Docker is left as it is.
   - nginx and certbot from the archive when missing, then one file in nginx's `conf.d/`, a proxy to the `web` container, with its certificate obtained by webroot. `nginx.conf` and other sites are never touched.
   - A deploy user whose SSH key is restricted to one command, `command="sudo /usr/local/sbin/balise-deploy \"$SSH_ORIGINAL_COMMAND\"",restrict`: sshd ignores the command the client sends, runs this one instead and puts the client's in `SSH_ORIGINAL_COMMAND`, which the deploy user's shell expands into a single argument before `sudo`, so sudo's cleared environment loses nothing. The one sudoers line allows that script with any argument; the script itself accepts only a version or `rollback` (point 7).
   - `/srv/balise`: the production `compose.yaml`, a root-only `.env` whose `APP_KEY` and database password are generated there once (ADR-0019 point 6), and the deploy script.
   - Optionally the firewall (SSH, HTTP, HTTPS) and unattended upgrades: on by default, off for a host that already manages them.
   Containers publish their ports on `127.0.0.1` only: Docker's published ports bypass the host firewall.
6. **The repository holds the templates; only the bootstrap applies them.** A deploy never reads a host file from the repository or the image. nginx's master process runs as root and opens log files itself, so an `access_log` line in a vhost written by CI could create any file as root; a `compose.yaml` written by CI could mount the host's disk into a container. Whoever can merge changes the code in the containers; only whoever runs the bootstrap changes the host. A leaked deploy key can redeploy an existing tag of the configured image, nothing else.
7. **A deploy is `balise-deploy <version>`**, called over SSH by the release workflow, or by hand. The script:
   1. accepts only a version (`v1.2.3`) or `rollback`;
   2. pulls both images at that version while the site keeps running;
   3. stops the queue worker and the scheduler, then runs `migrate --force` with the new image;
   4. starts every service on the new version;
   5. runs `network:import` for each network: a full import on an empty database, nothing when the provider's ETag is unchanged, the new data when it was published since the daily import;
   6. checks that `/up` answers 200, and starts the previous version again when it does not;
   7. renders the vhost from the host's template, and reloads nginx only when it changed and `nginx -t` passes;
   8. records the current and previous versions, and keeps the images of the last three.
   Migrations are never reversed automatically: a migration must keep the previous release working (add a column in one release, drop the old one in the next).
8. **Delivery stays as ADR-0004 decides:** release-please, with the token of a dedicated GitHub App so that its pull requests and releases trigger workflows. The deploy job runs on `release: published` in a GitHub environment `production` that holds the SSH key and the host's key fingerprint, accepts only tags `v*`, and waits for the maintainer's approval. The App can write tags and releases, so a stolen App key could tag any commit it pushed; the environment's approval, checked by GitHub and not by the workflow, keeps such a release off the server. A second ruleset requiring an approval to merge was tried and dropped: it did not cover that path, and GitHub offered no bypass for the maintainer's own pull requests while `main`'s ruleset also required a pull request.
9. **Proof on local machines first**, as in ADR-0019 point 8: three OrbStack machines, a fresh Debian 13, a fresh Ubuntu 26.04, and a Debian 13 that already runs Docker and nginx with another site, which must be left untouched. A second bootstrap run changes nothing.
10. **A new PostgreSQL major is moved by dump and restore.** PostgreSQL cannot open the data of an older major, and `pg_upgrade` needs both versions' binaries, which the official image lacks. When the label of the pulled image names another major than the running one, the deploy runs `pg_dumpall` with the old image, keeps the dump on the host, starts the new major on a new volume, restores it, and removes the old volume only once `/up` answers; the dump is the way back. Otherwise the data are not backed up: PostgreSQL and Redis keep named volumes, and everything in them still rebuilds itself (ADR-0012: positions from the feed, lines and stops from the import). The first story that stores data which do not rebuild (history, replay recordings) brings a real backup.

## Alternatives considered

| Option | Why not |
|---|---|
| The native install of E1.3b on the host | Needs the Ubuntu 26.04 archive (a Debian 13 host has PHP 8.4 and PostgreSQL 17), and puts PHP, PostgreSQL and system users on a machine that serves other things |
| Incus system container on the host, running the E1.3b playbooks | Keeps E1.3b, but adds Incus to the host and a network bridge to set up; Docker is already there |
| Oracle Cloud (ADR-0012) | Sign-up failed |
| A paid VPS | The maintainer does not want to pay |
| Deploy by a timer on the host that polls for releases | No key reaches the host, but the deploy result is not shown in Actions and starts minutes late |
| vhost and `compose.yaml` taken from the release | Gives root on the host to whoever can merge (decision point 6) |
| Keep `ubuntu:26.04` with the archive's PHP | PHP waits for the next Ubuntu LTS, and the parity it bought is gone |
| Every PHP target on `php:8.5-fpm-trixie`, so that the browser tests share production's libc | Measured in E1.3d: 773 MB for production, against 176 MB on Alpine, since the Debian image keeps its compilers; every deploy and CI job paid for it |
| A separate browser image beside a PHP image without Node | Pest launches Playwright itself and serves the application on `127.0.0.1` in its own container; it has no option to reach a remote browser |
| Docker from docker.com's repository | A third-party repository (ADR-0019 point 2); the archive's versions run compose files of this size |
| Kamal | Its proxy wants ports 80 and 443, which a host's own nginx already serves |
| Coolify, Dokploy | A control panel and its database on the host, for one application |
| Every version in `versions.yaml`, read by the Dockerfiles, `compose.yaml` and CI | Dockerfiles and compose read no file, only variables, and compose takes them from the `.env` that Laravel already uses, local and ignored by Git; the values would no longer be readable where they apply |
| A PostgreSQL major moved by deleting the volume | Fine while every row rebuilds itself, a loss once history or recordings exist |
| `pgautoupgrade/pgautoupgrade`, which runs `pg_upgrade` at start | A community image for the production database; its speed only matters for databases of several GB, this one holds tens of MB |
| Watchtower | Replaces containers when an image changes, with no migration step before it |
| Cloudflare in front now | The free plan needs the whole DNS zone; E1.4 decides |

## Consequences

- Anyone can deploy from the code: fork, fill the example inventory, run the bootstrap, set the secrets, publish a release (`docs/deploy.md`, E1.3d).
- `versions.yaml`, `scripts/check-versions.php`, their test and the required check `versions` are removed in E1.3d; the ruleset's required checks follow.
- ADR-0012 is superseded. Of ADR-0019, Ansible in a container, the archive rule and server-generated secrets stay; the host install (releases side by side, the `balise` user, systemd units, the build archive) gives way to the images, and `server.yml`, `deploy.yml` and `rollback.yml` are removed in E1.3d. ADR-0007 no longer says "no Docker in production".
- The extension list is written once, in `docker/php/install-extensions.sh`, which knows both package managers.
- Production images are the project's to maintain; the archive rule of ADR-0019 now applies only to what the bootstrap installs on the host (Docker, nginx, certbot).
- The host is amd64 while local images run arm64: the images are built on CI's amd64 runner, and an arm64 host would add a platform to the build.
- The stack shares the host's memory: measured on the server in E1.3e, with limits in the production `compose.yaml` if needed.
