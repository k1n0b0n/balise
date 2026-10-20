# Story 015: Server configured and deployed by Ansible, on a local machine

- **PRD:** E1.3b
- **Status:** Done

## Acceptance criteria

See E1.3b in [prd.md](../prd.md). Decisions in [ADR-0019](../adr/0019-server-configuration.md).

## Tasks

- [x] ADR-0019 accepted by the maintainer (Redis installed now, systemd over Supervisor, server-local secrets generated on it); PRD E1.3b criteria completed
- [x] `versions.yaml` and `compose.yaml`: Redis 8, the version of the Ubuntu 26.04 archive
- [x] `ansible` service in `compose.yaml` (profile `ops`): `python:3.14-alpine`, `ansible-core` 2.21.5 and two collections pinned in `ansible/`
- [x] Local machine: `scripts/local-server.sh`, an OrbStack Ubuntu 26.04 arm64 machine, isolated, 4 GB, with its own SSH key in `ansible/local/` (ignored by Git)
- [x] Server playbook (`ansible/server.yml`): packages from `packages.txt`, nginx with `docker/nginx/site.conf`, `php.ini`, the pool run as `balise`, PostgreSQL 18 with the application's database and user, Redis 8, `APP_KEY` and database password generated once, systemd units for the queue and the scheduler, firewall (SSH, HTTP, HTTPS), unattended upgrades; versions checked against `versions.yaml`
- [x] The second run of the server playbook changes nothing, and check mode reports no drift (`changed=0` both, also after deploys)
- [x] `scripts/build-release.sh`: archive of the committed HEAD (no dev packages, built assets, generated classes), made in the `test` image: 9 MB
- [x] Deploy playbook (`ansible/deploy.yml`): upload, `shared/.env` and `storage`, migrate, `optimize`, switch the link, reload php-fpm, restart the units, keep three releases; checks that the site answers 200
- [x] Rollback (`ansible/rollback.yml`) tried once, then the same release deployed again
- [x] On the machine: `network:import tbm` runs, the scheduler polls, the text view shows live vehicles; one TBM poll timed; everything comes back after the machine restarts
- [x] The map checked by the maintainer in a browser: vehicles move (headless Firefox has no WebGL, so the map is blank there, locally too)
- [x] Closing: *Current state*, ideas review (step 5)

## Notes

- Ubuntu 26.04's archive has Redis 8.0, not 7 (`apt-cache policy` in `ubuntu:26.04`, 2026-10-20); the application uses no Redis yet (cache, queue and sessions on the database), but the server installs it now, like `compose.yaml` (ADR-0019 point 2).
- **Bug found on the machine: positions two hours old.** OrbStack gives the machine the Mac's zone (Europe/Paris), and PostgreSQL takes the machine's zone at install. Laravel writes timestamps without an offset, so PostgreSQL read UTC times as Paris times: every position looked two hours old and the endpoint returned none. The `pgsql` connection now sets its session to UTC (`config/database.php`), like `app.timezone`; a test sets the test database to Europe/Paris and fails without it. Docker's PostgreSQL runs in UTC, which hid it. Oracle's image is in UTC too, but the application no longer depends on it.
- **Build directory permissions:** the archive's root carried `mktemp`'s 0700, which kept nginx (`www-data`) out of the release: "Primary script unknown". The build script opens it to 0755.
- **Workers wait for the first release:** their units have `ConditionPathExists=/var/www/balise/artisan`, so the server playbook only enables them and the deploy starts them; asking for "started" reported a change on every run.
- **After a restart, `schedule:work` waits for the next minute** before its 20 s task: a deploy skips up to three polls.
- **One TBM poll on the machine (M-series Mac): 0.30 to 0.32 s**, PHP start and download included, 445 positions changed (E2.3's open criterion; to measure again on Oracle's A1 in E1.3c).
- **Releases on disk:** `/srv/balise/releases/<build>`; `/var/www/balise` is the link to the live one, so `site.conf`, `compose.yaml` and the units name the same path everywhere.
- **Ideas review:** no idea is implemented here; every *Exploring* idea keeps its verdict. The overview's case for deployment first now has a server ready for E1.3c.
- **A browser may lack macOS's Local Network permission:** Firefox could not reach `balise-server.orb.local` nor the machine's IP until it was granted; OrbStack also forwards the machine's port 80 to `http://localhost/`.
- CodeRabbit reviewed the pull request: three findings, fixed. The deploy tested whether the release's `storage` was a link with `is link`, which looks at the controller: redeploying the live release removed its `storage` link for a moment (now a `stat` on the server, checked on a redeploy and a new release); ADR-0019 point 6 now says the secrets' own files decide, not `.env`; the PRD now names the firewall and unattended upgrades, which the playbook configures.
- **`.env` from a template, not a copy of `.env.example`** (maintainer's question): a key the template forgets falls back to the default of `config/` (`APP_DEBUG` is false there), whereas a copy would keep its development value (`APP_DEBUG=true`, `DB_HOST=postgres`). The cost, two lists of keys, is covered by `tests/Unit/EnvTemplateTest.php`: every key of `.env.example` is in `env.j2` or in a short list left to the defaults on purpose.
- **Not proven here** (the machine shares the Mac's kernel): the firewall's effect from outside, kernel settings. To check on Oracle (E1.3c).
