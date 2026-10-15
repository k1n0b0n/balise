# ADR-0006: Responsible design, RGAA and RGESN

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

- Two French public frameworks frame responsible digital services:
  - **RGAA 4.1.2** (accessibility): 106 criteria, aligned with WCAG 2.1 level AA. Legally required for public bodies and large companies, not for this project.
  - **RGESN 2024** (ecodesign): 78 criteria in 9 families. Voluntary outside the public sector.
- The core of the product is a live map, which is hard on both:
  - MapLibre renders into a canvas that screen readers cannot read.
  - Vehicles move on their own: moving content must be controllable (RGAA criterion 13.8).
  - Vehicles are told apart by line colour: information must not rely on colour alone (RGAA criterion 3.1).
  - Real-time updates are the main source of network and CPU usage.
- Automated accessibility checks (axe) find roughly a third of the issues; the rest needs a manual review.

## Decision

1. **Both frameworks are adopted voluntarily** and apply to every story through the non-functional requirements of the PRD.
2. **RGAA target: level AA** on the whole interface. For the map, an **equivalent text view** (lines and their vehicles, with the age of the last position) and a **pause control** for live updates provide the alternative.
3. **RGESN target: self-assessment** of the applicable criteria, kept up to date. Concrete commitments for real-time data:
   - only changed positions are sent to browsers;
   - updates stop while the tab is hidden;
   - basemap tiles and static assets are cached;
   - the refresh interval matches the feed's own update rate, not faster.
4. **Automated checks in the build:** axe through Pest browser tests on every page, from E3.1. A page weight and EcoIndex budget is measured on the map page and published.
5. **Public declarations:** an accessibility statement and an ecodesign statement, honest about partial conformance and listing known gaps.

## Alternatives considered

| Option | Why not |
|---|---|
| Ignore both until after the MVP | The text view and the update strategy shape the map stories; adding them later means reworking E3 and E4 |
| Claim full RGAA conformance | Requires a complete audit of the 106 criteria; an unaudited claim is less credible than an honest partial statement |
| WCAG only, no RGAA | RGAA is the French reference with a testing methodology; it includes WCAG AA |
| Automated checks only | They miss about two thirds of the issues |

## Consequences

- M1 gains three stories: accessible text view, pause control, public statements.
- The map stories keep line labels next to colours and check the contrast of line colours.
- The real-time design (E4) is constrained by the update commitments above.
- Each story review includes the relevant RGAA and RGESN criteria.
