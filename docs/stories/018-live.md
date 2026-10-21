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
- [ ] Deploy key and the `production` environment's secrets set by the maintainer
- [ ] A release through the workflow: images pushed to GHCR, packages made public, deploy approved; the public URL answers 200 over HTTPS
- [ ] Memory used by the stack and one TBM poll measured on the server
- [ ] Closing: story notes, *Current state*, ideas review

## Notes

- **A check on a host never prepared stopped halfway** (E1.3d proved the check only after a bootstrap): in check mode nothing is created, so the first task writing under `/srv/balise` failed. A host without `python3-apt` also refuses apt's check mode; Debian's standard install has it.
- **The Ansible container reaches the server through the Mac's SSH agent** (`/run/host-services/ssh-auth.sock`, which OrbStack and Docker Desktop expose), so no admin key is copied into `ansible/local/`; the host's key comes from the maintainer's `known_hosts`.
- **The first check on the server plans only additions:** the application directories, a new nginx site, its certificate, the deploy script, user, key and sudoers line. No package, firewall or upgrade change: Docker, nginx and certbot were there, and the inventory leaves the firewall and upgrades alone. Port 8080 was taken on the loopback, so the web container gets another one.
- **Bootstrap on the server:** first run `changed=15` with the certificate obtained by webroot, second run `changed=0`. Afterwards the server's nginx configuration still tests valid, its other sites, certificates and containers answer and run as before (none restarted), and the site redirects HTTP to HTTPS and answers 502 until the first deploy.
- **DNS first, and checked at the source:** a mistyped A record was caught by asking the zone's own name servers before the bootstrap; certbot would have failed against the wrong host. Only an A record for now; the server has a working public IPv6 and the site already listens on it, so an AAAA record can come later without other changes.
