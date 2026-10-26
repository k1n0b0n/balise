# Changelog

## [0.8.0](https://github.com/k1n0b0n/balise/compare/v0.7.0...v0.8.0) (2026-10-26)


### Features

* read trip updates on demand through a third port, decoded by the protobuf C extension (E8.1) ([#95](https://github.com/k1n0b0n/balise/issues/95)) ([8f20d03](https://github.com/k1n0b0n/balise/commit/8f20d03d942e6c7d65f442211d73ff15377b2018))
* replay a recorded service day locally (E5.2) ([#90](https://github.com/k1n0b0n/balise/issues/90)) ([fdc20bd](https://github.com/k1n0b0n/balise/commit/fdc20bdb50f926088112a60709faab3a7d69f5cb))
* show the stations on the map and find them by name (E7.1) ([#92](https://github.com/k1n0b0n/balise/issues/92)) ([2398368](https://github.com/k1n0b0n/balise/commit/23983689453ec6aedc0b31b756e9eb1c37e53736))


### Bug Fixes

* show a chosen station suggestion at once, say the centred map to screen readers only ([#96](https://github.com/k1n0b0n/balise/issues/96)) ([706c58b](https://github.com/k1n0b0n/balise/commit/706c58bcb1e46c154d450f59d5e9dd0214fbc4f2))

## [0.7.0](https://github.com/k1n0b0n/balise/compare/v0.6.0...v0.7.0) (2026-10-25)


### Features

* keep the positions' answers in nginx until the next version (E4.2) ([#86](https://github.com/k1n0b0n/balise/issues/86)) ([e7a10d3](https://github.com/k1n0b0n/balise/commit/e7a10d367fada959c0af9588102473005f933ed4))
* record the vehicle feed on the server, one public archive per service day (E5.1) ([#89](https://github.com/k1n0b0n/balise/issues/89)) ([11f15f7](https://github.com/k1n0b0n/balise/commit/11f15f74ebb4497f877aca23b2d0cc99caf2392e))


### Bug Fixes

* restart the web container when php is recreated alone ([#87](https://github.com/k1n0b0n/balise/issues/87)) ([71153de](https://github.com/k1n0b0n/balise/commit/71153dea39ee0d9b96d4d0362000661b94ac2a1d))

## [0.6.0](https://github.com/k1n0b0n/balise/compare/v0.5.0...v0.6.0) (2026-10-25)


### Features

* ask for the positions when the next version is expected (E4.1) ([#85](https://github.com/k1n0b0n/balise/issues/85)) ([d997236](https://github.com/k1n0b0n/balise/commit/d99723629776dc97f971a5c655ed7406357148d2))
* Redis for cache, queue and sessions, counted position versions, host and rollback guards (E1.8) ([#83](https://github.com/k1n0b0n/balise/issues/83)) ([bb34923](https://github.com/k1n0b0n/balise/commit/bb34923ef0ca2d260f270d5da668de5e7d30e4fe))


### Bug Fixes

* hardening from the code review (E1.7) ([#82](https://github.com/k1n0b0n/balise/issues/82)) ([6a2f3fe](https://github.com/k1n0b0n/balise/commit/6a2f3fe761d17a2336e49f9904bfcc1c2bbbfb50))

## [0.5.0](https://github.com/k1n0b0n/balise/compare/v0.4.0...v0.5.0) (2026-10-24)


### Features

* a "Signaler un problème" link to the repository's issue forms (E3.9) ([#75](https://github.com/k1n0b0n/balise/issues/75)) ([0ba57f5](https://github.com/k1n0b0n/balise/commit/0ba57f550d89f72480f92559646550ce8f0fb608))
* accessibility and ecodesign statements, from self-audits (E1.5) ([#78](https://github.com/k1n0b0n/balise/issues/78)) ([34ce52c](https://github.com/k1n0b0n/balise/commit/34ce52c51259d53e821ceea0338b513d7b303379))
* read the feed as soon as it is published (E2.5) ([#79](https://github.com/k1n0b0n/balise/issues/79)) ([11bcb14](https://github.com/k1n0b0n/balise/commit/11bcb14f41da0fd38374580c2fefeff303e6372d))


### Bug Fixes

* keep the line shapes in the database, which every container reads ([#81](https://github.com/k1n0b0n/balise/issues/81)) ([57ff689](https://github.com/k1n0b0n/balise/commit/57ff689d67b375a48ac8124dd5b863f9ceb44fab))
* send scheduled commands' logs to the container's logs (E1.6) ([#80](https://github.com/k1n0b0n/balise/issues/80)) ([d202fdf](https://github.com/k1n0b0n/balise/commit/d202fdfbdc85e5b9e38b2666e87ba3cc659811f5))

## [0.4.0](https://github.com/k1n0b0n/balise/compare/v0.3.0...v0.4.0) (2026-10-24)


### Features

* a lighter poll, with OPcache for the CLI (E2.4) ([#74](https://github.com/k1n0b0n/balise/issues/74)) ([753e9d1](https://github.com/k1n0b0n/balise/commit/753e9d1cc93d7acacd0565d1a3213e5cd1be9c3f))
* live on the maintainer's server (E1.3e) ([#64](https://github.com/k1n0b0n/balise/issues/64)) ([f766efe](https://github.com/k1n0b0n/balise/commit/f766efe502287c2617d160eb1d94500f3ac44d8b))
* raise PHP, PostgreSQL, Redis and Node every week (E1.3f) ([#68](https://github.com/k1n0b0n/balise/issues/68)) ([57ad6f8](https://github.com/k1n0b0n/balise/commit/57ad6f8e6afab1b0a01fde07d019d74a87808483))
* the line selector lists the running lines, by mode (E3.4) ([#66](https://github.com/k1n0b0n/balise/issues/66)) ([ad84047](https://github.com/k1n0b0n/balise/commit/ad840470f8163a5f13e6735850a0a96349e08da4))

## [0.3.0](https://github.com/k1n0b0n/balise/compare/v0.2.0...v0.3.0) (2026-10-21)


### Features

* Docker deploy on any Debian or Ubuntu host (E1.3d) ([#60](https://github.com/k1n0b0n/balise/issues/60)) ([12fbf30](https://github.com/k1n0b0n/balise/commit/12fbf30dcdd63f2c34841fba507dbd1d1d37b16a))

## [0.2.0](https://github.com/k1n0b0n/balise/compare/v0.1.0...v0.2.0) (2026-10-21)


### Features

* group vehicles by line and destination (E3.7) ([#53](https://github.com/k1n0b0n/balise/issues/53)) ([1b50174](https://github.com/k1n0b0n/balise/commit/1b501747b1592e830901fac8d3d4243b9bc5d1c0))

## 0.1.0 (2026-10-20)


### Features

* add Laravel skeleton with Docker stack and quality gates ([#4](https://github.com/k1n0b0n/balise/issues/4)) ([314f626](https://github.com/k1n0b0n/balise/commit/314f6269b9caf4a2844c350f42c377b20aefe453))
* add the text view and fetch line details once from a line catalogue ([#37](https://github.com/k1n0b0n/balise/issues/37)) ([0ee3dc9](https://github.com/k1n0b0n/balise/commit/0ee3dc9e8dae07b79a5bbc89c7ab3044e53a5886))
* configure and deploy the server with Ansible, proven on a local machine ([#56](https://github.com/k1n0b0n/balise/issues/56)) ([bbc0d78](https://github.com/k1n0b0n/balise/commit/bbc0d78f186613831a6ad136e34050b3dfc6acad))
* draw every line of the network on the map, faint, under the vehicles ([#43](https://github.com/k1n0b0n/balise/issues/43)) ([afcc6b3](https://github.com/k1n0b0n/balise/commit/afcc6b3dd743bc92ec6a64c5e6abb71812ab87c0))
* import lines and stops from a network's static GTFS ([#22](https://github.com/k1n0b0n/balise/issues/22)) ([6b0c4c4](https://github.com/k1n0b0n/balise/commit/6b0c4c47f7007eda772d2bbc16781e0351d8c801))
* keep platform versions in one file, checked in CI (E1.3a) ([#55](https://github.com/k1n0b0n/balise/issues/55)) ([993702b](https://github.com/k1n0b0n/balise/commit/993702b58a4c28f66b86c00c8c2e20e47cf2cf43))
* keep the latest fresh position per vehicle and announce changes ([#27](https://github.com/k1n0b0n/balise/issues/27)) ([1570ef3](https://github.com/k1n0b0n/balise/commit/1570ef32cdba1d29ffe2cf9bd216447b82dec258))
* open a vehicle popup on click with the line, id and age of its position ([#34](https://github.com/k1n0b0n/balise/issues/34)) ([b154182](https://github.com/k1n0b0n/balise/commit/b1541828489397b715151b2cf0da36a5ec9a743b))
* pause and resume live updates of the map and the text view ([#41](https://github.com/k1n0b0n/balise/issues/41)) ([5a31f90](https://github.com/k1n0b0n/balise/commit/5a31f9042e4bf1eec3a5bfc12a0ab675bdf1a792))
* poll live vehicle positions from GTFS-Realtime feeds ([#26](https://github.com/k1n0b0n/balise/issues/26)) ([16d0a6b](https://github.com/k1n0b0n/balise/commit/16d0a6b0bb04114b14b32b5590e8a04441b01dcf))
* releases and version upkeep (E1.3c) ([#57](https://github.com/k1n0b0n/balise/issues/57)) ([2357fc1](https://github.com/k1n0b0n/balise/commit/2357fc198d1c957232be4f63ae8a602d311661f8))
* select a line or a vehicle and let the map follow it ([#45](https://github.com/k1n0b0n/balise/issues/45)) ([a5b4b93](https://github.com/k1n0b0n/balise/commit/a5b4b939d7793449e57a6c5cf27d43fbfd45e2d1))
* show the MapLibre map page with accessibility and weight checks ([#30](https://github.com/k1n0b0n/balise/issues/30)) ([4c0ed19](https://github.com/k1n0b0n/balise/commit/4c0ed197e244baf856dfbe54b625cc88625f6e2b))
* show vehicles coloured by line with accessible groups ([#32](https://github.com/k1n0b0n/balise/issues/32)) ([2604ca9](https://github.com/k1n0b0n/balise/commit/2604ca92c4e7df001afd80fb01ad9593da4edeff))


### Bug Fixes

* deselect a line from the header or by closing the popup, and apply the selector on change ([#48](https://github.com/k1n0b0n/balise/issues/48)) ([f9bfa53](https://github.com/k1n0b0n/balise/commit/f9bfa5306a2051a79a87d3570cea23adbb2b17bc))
* keep long straight steps of a shape, cut only those that turn sharply ([#49](https://github.com/k1n0b0n/balise/issues/49)) ([05e6a3a](https://github.com/k1n0b0n/balise/commit/05e6a3ae0a406a6516658b6e5d817f8721950167))
* name the line in each vehicle button of the text view ([#39](https://github.com/k1n0b0n/balise/issues/39)) ([d170438](https://github.com/k1n0b0n/balise/commit/d1704388e163584e8336a46b0fa5cd0803d32efe))
