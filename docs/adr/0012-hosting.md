# ADR-0012: Hosting, Oracle Cloud Always Free provisioned as code

- **Status:** Superseded by ADR-0020 (2026-10-21: the Oracle sign-up failed)
- **Date:** 2026-10-16

## Context

- One public demo: PHP-FPM, nginx, PostgreSQL 18, Redis, queue worker, scheduler and Reverb on one machine (ADR-0002, ADR-0007). About 4 GB of RAM is comfortable for that set.
- The maintainer does not want to pay for hosting.
- Almost every piece of data rebuilds itself: positions come back from the live feed within seconds, lines and stops from the static GTFS import, and there are no user accounts (brief). The rest is loaded by seeders.
- Users are mostly in France; data hosted in the EU.

### Offers compared (2026-10-16, monthly)

| Option | Size | Cost |
|---|---|---|
| **Oracle Cloud Always Free**, Ampere A1 (arm64), home region Paris | 2 OCPU, 12 GB in total since June 2026 | €0 |
| Scaleway Dedibox VPS-PRO-2-S | 2 vCPU, 4 GB | €10.49 |
| Hetzner CX23 | 2 vCPU, 4 GB | €5.49 plus IPv4, out of stock since September 2026 |
| Render, Koyeb, Railway free tiers | Sleeping services, expiring databases or credits | €0, but cannot keep a live demo with Reverb and a queue |

## Decision

1. **One Oracle Cloud Always Free A1 instance** (arm64, Ubuntu 26.04) in the Paris region, sized within the free allowance.
2. **The server is code, in the repository:**
   - **OpenTofu** with the official OCI provider creates the instance, network and firewall. State is kept in Oracle Object Storage, encrypted with OpenTofu state encryption.
   - **Ansible** configures the machine from the files ADR-0007 already versions (nginx site, `php.ini`, php-fpm pool, PHP packages, daemons) and the versions file of E1.3.
   - Rebuilding from nothing is one `tofu apply` plus one playbook run, then the GTFS import and the seeders.
3. **Cloudflare free plan** in front: DNS, proxy, TLS and cache rules (E1.4). Whether the origin is reached through its public IP or a Cloudflare Tunnel is decided in E1.4.
4. **Deploys run from GitHub Actions and Ansible** (ADR-0004, amended).
5. **No idle tricks.** Oracle may reclaim an A1 instance it deems idle; the answer is a fast rebuild, not artificial load.

## Alternatives considered

| Option | Why not |
|---|---|
| Scaleway Dedibox VPS | Paid |
| Hetzner | Good value, but paid and the 4 GB plans are out of stock |
| Free PaaS tiers (Render, Koyeb, Railway) | Services sleep, free databases expire, credits run out |
| Kamal, Coolify, Dokploy | Need a production Docker image (excluded by ADR-0007) or add a heavy layer on a small machine |
| The maintainer's existing server | Shared with other services, and no Ubuntu parity for a native install |

## Consequences

- Hosting costs nothing; Cloudflare and Oracle accounts are the only prerequisites.
- Free-tier risks are accepted: Oracle can reclaim or stop the instance, A1 capacity may be short when recreating, and Oracle changed the free limits without notice in 2026. A reclaimed instance is restarted; a lost one is rebuilt from the repository in about half an hour.
- The server is arm64 while CI runs on x86: PHP, Composer and Vite output do not depend on the architecture, and local development already runs arm64 images.
- OCI, SSH and Cloudflare credentials live in GitHub secrets and in the encrypted OpenTofu state, never in the repository.
- A domain name is needed before E1.3 and E1.4.
