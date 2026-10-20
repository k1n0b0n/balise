# ADR-0020: Hosting with Docker on a server prepared by Ansible

- **Status:** Proposed
- **Date:** 2026-10-21

## Context

- The Oracle Cloud account of ADR-0012 could not be created (sign-up failed after the identity check, 2026-10-16), and the maintainer does not want to pay for hosting.
- The maintainer already owns a dedicated server that runs other services. It has Docker and an nginx in front of them, on a distribution other than Ubuntu 26.04. The repository is public: the server's name, address, port and services stay out of it.
- E1.3b installs PHP 8.5 and PostgreSQL 18 on the host from the Ubuntu 26.04 archive (ADR-0019). A Debian 13 host has neither (PHP 8.4, PostgreSQL 17), and installing them would touch a machine that serves other things.
- The local images already run Ubuntu 26.04 with the archive's PHP (ADR-0007). Point 6 of ADR-0007 leaves Docker in production to its own ADR, with a `production` target built from `runtime`.
- Anyone should be able to deploy the project from the code onto a machine of their own, with templates and a documented process.
- Measured on 2026-10-21 in the archives: Debian 13 has `docker.io` 26.1, `docker-compose` 2.26, nginx 1.26 and certbot 4.0; Ubuntu 26.04 has `docker.io` 29.1, `docker-compose-v2` 2.40, nginx 1.28 and certbot 4.0.

## Decision

1. **The host is any Debian 13 or Ubuntu 26.04 machine reachable by SSH**, freshly installed or already running other services. The demo runs on the maintainer's server; its name, address, SSH port and domain live in GitHub secrets and variables and in a local inventory ignored by Git, never in the repository.
2. **Two production images, built on release** by GitHub Actions for `linux/amd64` and pushed, public, to the GitHub Container Registry of the repository's owner (`ghcr.io/<owner>/balise-app`, `…/balise-web`), tagged with the release version:
   - `app`: a `production` target of `docker/php/Dockerfile` built from `runtime`, with what `scripts/build-release.sh` puts in the archive (code, Composer without dev packages, built assets, generated GTFS-Realtime classes). php-fpm, the queue worker, the scheduler and one-off commands run it; each container runs `php artisan optimize` at start, since `.env` only exists on the host.
   - `web`: the nginx image of `docker/nginx/` with `public/`.
3. **One bootstrap playbook, `ansible/host.yml`, run by a person with root** from the `ansible` service (ADR-0019 point 1). It installs, from the templates in `ansible/`:
   - Docker and its compose plugin from the distribution's archive when missing; an installed Docker is left as it is.
   - nginx and certbot from the archive when missing, then one file in nginx's `conf.d/`, a proxy to the `web` container, with its certificate obtained by webroot. `nginx.conf` and other sites are never touched.
   - A deploy user whose SSH key is restricted to one command (`command="sudo /usr/local/sbin/balise-deploy",restrict`), and the one sudoers line that allows it.
   - `/srv/balise`: the production `compose.yaml`, a root-only `.env` whose `APP_KEY` and database password are generated there once (ADR-0019 point 6), and the deploy script.
   - Optionally the firewall (SSH, HTTP, HTTPS) and unattended upgrades: on by default, off for a host that already manages them.
   Containers publish their ports on `127.0.0.1` only: Docker's published ports bypass the host firewall.
4. **The repository holds the templates; only the bootstrap applies them.** A deploy never reads a host file from the repository or the image. nginx's master process runs as root and opens log files itself, so an `access_log` line in a vhost written by CI could create any file as root; a `compose.yaml` written by CI could mount the host's disk into a container. Whoever can merge changes the code in the containers; only whoever runs the bootstrap changes the host. A leaked deploy key can redeploy an existing tag of the configured image, nothing else.
5. **A deploy is `balise-deploy <version>`**, called over SSH by the release workflow, or by hand. The script:
   1. accepts only a version (`v1.2.3`) or `rollback`;
   2. pulls both images at that version while the site keeps running;
   3. stops the queue worker and the scheduler, then runs `migrate --force` with the new image;
   4. starts every service on the new version;
   5. runs `network:import` for each network: a full import on an empty database, nothing when the provider's ETag is unchanged, the new data when it was published since the daily import;
   6. checks that `/up` answers 200, and starts the previous version again when it does not;
   7. renders the vhost from the host's template, and reloads nginx only when it changed and `nginx -t` passes;
   8. records the current and previous versions, and keeps the images of the last three.
   Migrations are never reversed automatically: a migration must keep the previous release working (add a column in one release, drop the old one in the next).
6. **Delivery stays as ADR-0004 decides:** release-please, with the token of a dedicated GitHub App so that its pull requests and releases trigger workflows. The deploy job runs on `release: published` in a GitHub environment `production` that holds the SSH key and the host's key fingerprint, and accepts only tags `v*`.
7. **Proof on local machines first**, as in ADR-0019 point 8: three OrbStack machines, a fresh Debian 13, a fresh Ubuntu 26.04, and a Debian 13 that already runs Docker and nginx with another site, which must be left untouched. A second bootstrap run changes nothing.
8. **The data are not backed up.** PostgreSQL and Redis keep named volumes; everything in them rebuilds itself (ADR-0012: positions from the feed, lines and stops from the import).

## Alternatives considered

| Option | Why not |
|---|---|
| The native install of E1.3b on the host | Needs the Ubuntu 26.04 archive (a Debian 13 host has PHP 8.4 and PostgreSQL 17), and puts PHP, PostgreSQL and system users on a machine that serves other things |
| Incus system container on the host, running the E1.3b playbooks | Keeps E1.3b, but adds Incus to the host and a network bridge to set up; Docker is already there |
| Oracle Cloud (ADR-0012) | Sign-up failed |
| A paid VPS | The maintainer does not want to pay |
| Deploy by a timer on the host that polls for releases | No key reaches the host, but the deploy result is not shown in Actions and starts minutes late |
| vhost and `compose.yaml` taken from the release | Gives root on the host to whoever can merge (decision point 4) |
| Docker from docker.com's repository | A third-party repository (ADR-0019 point 2); the archive's versions run compose files of this size |
| Kamal | Its proxy wants ports 80 and 443, which a host's own nginx already serves |
| Coolify, Dokploy | A control panel and its database on the host, for one application |
| Watchtower | Replaces containers when an image changes, with no migration step before it |
| Cloudflare in front now | The free plan needs the whole DNS zone; E1.4 decides |

## Consequences

- Anyone can deploy from the code: fork, fill the example inventory, run the bootstrap, set the secrets, publish a release (`docs/deploy.md`, E1.3d).
- ADR-0012 is superseded. Of ADR-0019, Ansible in a container, the archive rule and server-generated secrets stay; the host install (releases side by side, the `balise` user, systemd units, the build archive) gives way to the images, and `server.yml`, `deploy.yml` and `rollback.yml` are removed in E1.3d. ADR-0007 no longer says "no Docker in production".
- Production images are the project's to maintain; the archive rule now applies to the images only, which already follow it.
- The host is amd64 while local images run arm64: the images are built on CI's amd64 runner, and an arm64 host would add a platform to the build.
- The stack shares the host's memory: measured on the server in E1.3e, with limits in the production `compose.yaml` if needed.
