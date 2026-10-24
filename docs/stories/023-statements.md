# Story 023: Accessibility and ecodesign statements

- **PRD:** E1.5
- **Status:** In progress

## Acceptance criteria

See E1.5 in [prd.md](../prd.md).

## Tasks

- [x] RGAA self-audit of the map page, with and without a selected line: `resources/audits/rgaa.json`, 106 criteria, a test that none is missing
- [x] Fixes found by the audit: page title (8.6), English basemap credits marked `lang="en"` (8.7), the map's label referring to the text view (1.6), a valid `time` in the text view's template (8.2)
- [ ] `/accessibilite` and `/ecoconception`, audited too, and the footer linking them from every page
- [ ] RGESN 2024 self-assessment: `resources/audits/rgesn.json`, 78 criteria

## Notes

- **Sources.** The criteria and their particular cases come from the official `RGAA/criteres.json` of `DISIC/accessibilite.numerique.gouv.fr` (RGAA 4.1.2). The audit says what was checked for each criterion, in English like the rest of the repository; the statement says it in French.
- **The map page: 56 criteria conformant, 50 not applicable, none failing after four fixes.** Before them it failed 1.6 (the canvas did not refer to its detailed description), 8.2 (an empty `time` in a template), 8.6 (the title was "balise" for every page) and 8.7 (the style's credits, "Data from OpenStreetMap", were read as French).
- **How it was checked.** Chromium through Playwright at 1280, 640 (200 %) and 320 px: tab order and names of every control, landmarks and headings, horizontal scroll, text spacing overridden (WCAG 1.4.12 values), stylesheets removed; the W3C Nu validator on the server's HTML; axe through the browser tests; VoiceOver with Firefox and Chrome from story 009.
- **8.2 and framework attributes.** The validator lists Alpine's and Livewire's attributes (`x-data`, `wire:snapshot`…) as not allowed; the RGAA test checks writing rules, nesting, unique ids and duplicate attributes, which they respect, so they do not fail it.
- **12.1, two navigation systems: not applicable.** The RGAA's particular case for a very limited number of pages all reachable from the home page: the map and the two statements.
