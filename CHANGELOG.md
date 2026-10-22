# Changelog

## [0.4.0](https://github.com/k1n0b0n/balise/compare/v0.3.0...v0.4.0) (2026-10-22)


### Features

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
