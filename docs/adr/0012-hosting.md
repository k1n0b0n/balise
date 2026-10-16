# ADR-0012: Hosting, OVHcloud VPS managed by Laravel Forge behind Cloudflare

- **Status:** Accepted
- **Date:** 2026-10-16

## Context

- One public demo: PHP-FPM, nginx, PostgreSQL 18, Redis, queue worker, scheduler and Reverb on one machine (ADR-0002, ADR-0007). About 4 GB of RAM is comfortable for that set; 2 GB is tight once PostgreSQL and Reverb run.
- Deployment goes through Forge on release (ADR-0004); the job posting the project answers names Forge, Laravel Cloud and Cloudflare.
- The server runs Ubuntu 26.04, Forge's default for new servers (ADR-0007).
- Users are mostly in France (TBM, Bordeaux); data hosted in the EU.
- Budget: a personal project, paid monthly, no commitment.

### Offers compared (2026-10-16, monthly, before tax)

| Option | Size | Price | Forge integration |
|---|---|---|---|
| OVHcloud VPS-1 (France) | 2 vCores, 4 GB, 40 GB NVMe, unlimited traffic, daily backup of the last 24 h | about €4.50 to €6.50 (sources disagree after the March 2026 increase; checked at order) | Custom server: Forge Hobby's one external server |
| Scaleway Dedibox VPS-PRO-2-S (France; existing Online.net account) | 2 vCPU, 4 GB, 75 GB NVMe, 400 Mbps | €10.49 | Custom server |
| Scaleway Dedibox VPS-START-2-S | 2 vCPU, 2 GB, 30 GB NVMe | €6.99 | Custom server |
| Hetzner CX23 (Germany, Finland) | 2 vCPU, 4 GB | €5.49 plus IPv4, but marked unavailable since September 2026 | Native provider |
| Laravel VPS | 1 vCPU, 1 GB at the entry size | from $6; larger sizes and EU regions not checked | Native, does not use the external server slot |
| Laravel Cloud | Serverless, managed | Usage-based | Not Forge |

Forge Hobby costs $12 per month and includes one external server.

## Decision

1. **OVHcloud VPS-1**, in a French datacentre, with a fresh Ubuntu 26.04 image, connected to Forge as a custom server on the **Hobby** plan.
2. **Cloudflare free plan** in front: DNS, proxy, TLS and cache rules (E1.4). Reverb's WebSockets go through the proxy.
3. **One server for everything.** Splitting database, cache or workers waits for the load test (E5.3) to show the need.
4. Total running cost: about €17 per month (Forge plus VPS), no commitment.

## Alternatives considered

| Option | Why not |
|---|---|
| Scaleway Dedibox VPS (existing account) | Same French hosting and no new account, but twice the price of VPS-1 for 4 GB (PRO-2-S); the 2 GB size at a similar price is too tight. Fallback if OVH's price or Ubuntu 26.04 image disappoints at order |
| Hetzner | Native Forge provider and good value, but the 4 GB cost-optimised plans are out of stock and the 2026 increases removed most of the gap |
| Laravel VPS | Instant provisioning and no external server slot used, but the entry size is too small and larger sizes and EU regions were not verified; it would also hide the server work the project should show |
| Laravel Cloud | Managed and named in the posting, but usage-based cost for a demo meant to stay online, and it bypasses the Forge workflow of ADR-0004. Possible later experiment, not the MVP host |
| The maintainer's existing server | It runs personal mail; a public demo would share its resources and its risk |

## Consequences

- The OVH VPS is the Forge Hobby plan's only external server: a second one (staging, second network) means the Growth plan or Laravel VPS.
- Ubuntu 26.04 image availability and the actual price are checked when ordering; Scaleway VPS-PRO-2-S is the fallback.
- The provider's daily backup covers the last 24 h only; database backups to off-site storage are their own decision when E1.3 is written.
- A domain name is needed before E1.3 and E1.4.
- Server versions (Ubuntu, PHP, PostgreSQL, Redis, Node) are kept in line with the repository as described in E1.3.
