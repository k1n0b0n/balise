# Story 014: Platform versions in one file

- **PRD:** E1.3a
- **Status:** In progress

## Acceptance criteria

See E1.3a in [prd.md](../prd.md).

## Tasks

- [x] PRD: E1.3 split into E1.3a (versions), E1.3b (Ansible on a local virtual machine), E1.3c (Oracle Cloud, deploy on release), so that what needs no cloud account comes first (Oracle sign-up still pending)
- [x] `versions.yaml` at the root: Ubuntu, PHP, PostgreSQL, Redis, Node; flat `key: "value"` pairs, so that Ansible reads it as variables (E1.3b) and a script without dependencies can parse it
- [x] `scripts/check-versions.php`: every place that names a version agrees with the file, else it lists file, line, expected and found, and exits 1
- [x] Fix what the check finds: the `test` image runs Node 22 from the Ubuntu archive, Vite and CI run Node 24
- [ ] CI: a `versions` job, fast and separate from `ci` (done), required by the `main` ruleset (once it has run on this pull request)
- [x] Tests: the check passes on the repository and fails, naming the place, when one file disagrees

## Notes

- **The check found one disagreement at once:** the `test` image installed `nodejs` from the Ubuntu 26.04 archive, which is Node 22, while Vite and CI run Node 24. The image now copies Node from the official `node:24-slim` image; Playwright's Chromium is unchanged.
- **Flat `key: "value"` YAML:** Ansible reads it as variables, and the check parses it with one regular expression, so CI runs it with the runner's own PHP before installing anything.
- **Every pattern must match at least once:** a rewritten file (say `FROM debian:`) fails the check instead of escaping it.
- **Comment lines are ignored** (CodeRabbit): a pin left in a comment no longer passes for the real one, and quoted values (`node-version: '24'`) are read. This dropped `docker/nginx/upstream-docker.conf`, which names PHP only in a comment; the server's real upstream will come from Ansible (E1.3b), which reads `versions.yaml`.
- **Not checked:** the runner image of CI (`ubuntu-24.04`, GitHub's choice; PHP and Node there come from `setup-php` and `setup-node`, which are checked), and the versions written in the docs (`AGENTS.md`, ADRs).
