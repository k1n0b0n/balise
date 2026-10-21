# Story 018: Live on the maintainer's server

- **PRD:** E1.3e
- **Status:** In progress

## Acceptance criteria

See E1.3e in [prd.md](../prd.md). Decisions in [ADR-0020](../adr/0020-docker-hosting.md); the steps are those of
[deploy.md](../deploy.md). Nothing about the server (name, address, port, users, other services) goes into the
repository: it stays in the ignored inventory and the `production` environment's secrets.

## Tasks

- [x] A first `--check --diff` runs to the end on a host that already serves other things but was never prepared, as `docs/deploy.md` asks; proven on a fresh shared OrbStack machine
- [x] The server's inventory in `ansible/local/`, its DNS record, and a `--check --diff` on it read with the maintainer
- [x] Bootstrap run by the maintainer; a second run changes nothing; the server's other sites and containers left as they were
- [x] Deploy key and the `production` environment's secrets set by the maintainer
- [x] A release through the workflow: images pushed to GHCR, packages made public, deploy approved; the public URL answers 200 over HTTPS
- [x] Memory used by the stack and one TBM poll measured on the server
- [ ] Closing: story notes, *Current state*, ideas review

## Notes

- **A check on a host never prepared stopped halfway** (E1.3d proved the check only after a bootstrap): in check mode nothing is created, so the first task writing under `/srv/balise` failed. A host without `python3-apt` also refuses apt's check mode; Debian's standard install has it.
- **The Ansible container reaches the server through the Mac's SSH agent** (`/run/host-services/ssh-auth.sock`, which OrbStack and Docker Desktop expose), so no admin key is copied into `ansible/local/`; the host's key comes from the maintainer's `known_hosts`.
- **The first check on the server plans only additions:** the application directories, a new nginx site, its certificate, the deploy script, user, key and sudoers line. No package, firewall or upgrade change: Docker, nginx and certbot were there, and the inventory leaves the firewall and upgrades alone. Port 8080 was taken on the loopback, so the web container gets another one.
- **Bootstrap on the server:** first run `changed=15` with the certificate obtained by webroot, second run `changed=0`. Afterwards the server's nginx configuration still tests valid, its other sites, certificates and containers answer and run as before (none restarted), and the site redirects HTTP to HTTPS and answers 502 until the first deploy.
- **DNS first, and checked at the source:** a mistyped A record was caught by asking the zone's own name servers before the bootstrap; certbot would have failed against the wrong host. Only an A record for now; the server has a working public IPv6 and the site already listens on it, so an AAAA record can come later without other changes.
- **First release through the workflow, `v0.3.0`:** the `images` job built and pushed both images in 2 min 10 s; the deploy job, approved half an hour later once the secrets were set (a job reads them when it starts), deployed in 2 min 24 s: pull 48 s, migrations on the empty database 50 s, start 9 s, `/up` at once, import of 202 lines and 5,234 stops 29 s. The public URL answers 200 over HTTPS, built assets are served gzipped with a year's cache, no `http://` link is left in the page, and 406 vehicles were in the database a minute later.
- **The packages were public without being changed:** an anonymous pull of both `v0.3.0` manifests answered 200 before the deploy. `docs/deploy.md` said to change their visibility by hand; it now says to check.
- **Memory on the server:** 216 MiB for the six containers a few minutes after the deploy (scheduler 75, postgres 47, php 45, queue 37, redis 8, web 4), on a host with 3.8 GiB already running a mail server.
- **One TBM poll on the server** (E2.3; 443 vehicles read, about 410 changed): `network:poll tbm` takes 1.34 s (median of five, 1.29 to 2.48), of which 0.71 s is starting Artisan and 0.13 s downloading the 59 KB feed, so about 0.5 s of processing, against 0.35 s for the whole run locally. Each scheduled run is a new PHP process and the CLI runs without OPcache (`opcache.enable_cli` off), on two vCPUs shared with the mail server's antivirus. Well inside the 20 s between polls; E2.3's criterion of one second holds for the processing, not for the whole command.
