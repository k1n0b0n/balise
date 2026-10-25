# Story 018: Live on the maintainer's server

- **PRD:** E1.3e
- **Status:** Done

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
- [x] Closing: story notes, *Current state*, ideas review

## Notes

- **A check on a host never prepared stopped halfway** (E1.3d proved the check only after a bootstrap): in check mode nothing is created, so the first task writing under `/srv/balise` failed. A host without `python3-apt` also refuses apt's check mode; Debian's standard install has it.
- **The Ansible container reaches the server through the workstation's SSH agent** (`/run/host-services/ssh-auth.sock`, which OrbStack and Docker Desktop expose), so no admin key is copied into `ansible/local/`; the host's key comes from the maintainer's `known_hosts`.
- **The first check on the server plans only additions:** the application directories, a new nginx site, its certificate, the deploy script, user, key and sudoers line. No package, firewall or upgrade change: Docker, nginx and certbot were there, and the inventory leaves the firewall and upgrades alone. Port 8080 was taken on the loopback, so the web container gets another one.
- **Bootstrap on the server:** first run `changed=15` with the certificate obtained by webroot, second run `changed=0`. Afterwards the server's nginx configuration still tests valid, its other sites, certificates and containers answer and run as before (none restarted), and the site redirects HTTP to HTTPS and answers 502 until the first deploy.
- **DNS first, and checked at the source:** a mistyped A record was caught by asking the zone's own name servers before the bootstrap; certbot would have failed against the wrong host. Only an A record for now; the server has a working public IPv6 and the site already listens on it, so an AAAA record can come later without other changes.
- **First release through the workflow, `v0.3.0`:** the `images` job built and pushed both images in 2 min 10 s; the deploy job, approved half an hour later once the secrets were set (a job reads them when it starts), deployed in 2 min 24 s: pull 48 s, migrations on the empty database 50 s, start 9 s, `/up` at once, import of 202 lines and 5,234 stops 29 s. The public URL answers 200 over HTTPS, built assets are served gzipped with a year's cache, no `http://` link is left in the page, and 406 vehicles were in the database a minute later.
- **The packages were public without being changed:** an anonymous pull of both `v0.3.0` manifests answered 200 before the deploy. `docs/deploy.md` said to change their visibility by hand; it now says to check.
- **Memory on the server:** 216 MiB for the six containers a few minutes after the deploy (scheduler 75, postgres 47, php 45, queue 37, redis 8, web 4), on a host with 3.8 GiB already running a mail server.
- **One TBM poll on the server** (E2.3; about 460 vehicles read, 430 changed): `network:poll tbm` takes 1.34 s (median of five, 1.29 to 2.48). Timed piece by piece in one process, three times: booting Laravel 0.48 to 0.59 s, downloading the 59 KB feed 0.22 s, decoding the protobuf 0.28 to 0.31 s, `RecordPositions` 0.54 s. So about 0.8 s of processing, against 0.35 s for the whole run locally, on two vCPUs shared with the mail server's antivirus. Each scheduled run is a new PHP process, and the CLI runs without OPcache: with `opcache.enable_cli` and a file cache, tried in the container without deploying it, `artisan --version` went from 0.71 s to about 0.35 s once the cache was filled, and the boot of the timed script from 0.5 s to 0.15 s; decoding and recording do not change. `RecordPositions` loads every stored position as a model, upserts the changed ones and loads them again for the event. All of it is well inside the 20 s between polls.
- **E2.3's criterion is ticked on the processing** (about 0.8 s), not on the whole command (1.34 s); OPcache for the CLI and a lighter `RecordPositions` go to the PRD's *Later*. The protobuf C extension would save about 0.3 s a poll, not enough for its ADR, build stage and hand-kept version before the trip updates port, where it saves 1.2 s and 330 MB a read.
- **Ideas review:** no idea planned this story. *Next departures* stays Exploring; the overview's argument for deploying first now reads that the MVP is live. The PRD's *Later* item for a Docker deployment option, done by E1.3d, is removed.
