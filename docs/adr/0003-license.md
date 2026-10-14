# ADR-0003: MIT license for the code

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

- The repository is public. Without a license, the code is "all rights reserved": readable, but not reusable.
- The primary audience is technical reviewers (brief). The long-term vision is a service for transit users.
- Changing license after external contributions may require every contributor's consent, so the choice has to be made before the first one.
- The code license is not the data license. TBM data is under the Licence Ouverte (Etalab 2.0) and basemap data comes from OpenStreetMap (ODbL); both require attribution.

## Decision

1. **The code is released under the MIT license** (`LICENSE` at the root).
2. **No CLA or DCO** while the project has a single maintainer.
3. **Data attribution is separate from the code license:** the README and the map UI credit TBM / Bordeaux Métropole (Licence Ouverte) and OpenStreetMap contributors (ODbL). Recorded feed snapshots committed as fixtures carry the same attribution.

## Alternatives considered

| Option | Why not |
|---|---|
| AGPL-3.0 | Prevents closed hosted forks, but blocks reuse of any part of the code in proprietary projects. The protection is mostly theoretical for this project, and moving from AGPL back to a permissive license needs every contributor's consent |
| Apache-2.0 | Adds an explicit patent grant, irrelevant for this project; longer and less common in the Laravel ecosystem |
| No license | Contradicts the goal of a public, reusable project |

## Consequences

- Anyone can reuse the code, including in closed projects, with the copyright notice kept.
- The decision stays reversible in one direction: MIT code, including contributions, can be relicensed under AGPL-3.0 later without anyone's consent. Versions already released under MIT remain MIT.
- The map UI needs an attribution control from E3.1 (already provided by MapLibre for the basemap).
