# Story 023: Accessibility and ecodesign statements

- **PRD:** E1.5
- **Status:** Done

## Acceptance criteria

See E1.5 in [prd.md](../prd.md).

## Tasks

- [x] RGAA self-audit of the map page, with and without a selected line, and of both statements: `resources/audits/rgaa.json`, 106 criteria, a test that no criterion or page is missing
- [x] Fixes found by the audit: page title (8.6), English basemap credits marked `lang="en"` (8.7), the map's label referring to the text view (1.6), a valid `time` in the text view's template (8.2)
- [x] `/accessibilite` and `/ecoconception`, rendered by the server from both files (`App\Support\Statements`), without Livewire's script; a footer on every page links both, with the conformance status, and the report link of E3.9, moved there from the header
- [x] RGESN 2024 self-assessment: `resources/audits/rgesn.json`, 78 criteria with their official titles and priorities, a test that none is missing
- [x] Tests: both pages and their figures, the footer on every page, axe and the skip link in the browser

## Notes

- **Sources.** The criteria and their particular cases come from the official `RGAA/criteres.json` of `DISIC/accessibilite.numerique.gouv.fr` (RGAA 4.1.2). The audit says what was checked for each criterion, in English like the rest of the repository; the statement says it in French.
- **Result: 57 criteria conformant, 49 not applicable, none failing, so "totalement conforme" at 100 %** on the four pages, a self-audit that the statement says is not independent. The map page passed 56 after four fixes, and the footer made 12.2 (navigation in the same place) applicable and conformant.
- **The map page's four failures, fixed.** Before them it failed 1.6 (the canvas did not refer to its detailed description), 8.2 (an empty `time` in a template), 8.6 (the title was "balise" for every page) and 8.7 (the style's credits, "Data from OpenStreetMap", were read as French).
- **How it was checked.** Chromium through Playwright at 1280, 640 (200 %) and 320 px: tab order and names of every control, landmarks and headings, horizontal scroll, text spacing overridden (WCAG 1.4.12 values), stylesheets removed; the W3C Nu validator on the server's HTML; axe through the browser tests; VoiceOver with Firefox and Chrome from story 009.
- **8.2 and framework attributes.** The validator lists Alpine's and Livewire's attributes (`x-data`, `wire:snapshot`…) as not allowed; the RGAA test checks writing rules, nesting, unique ids and duplicate attributes, which they respect, so they do not fail it.
- **The statements' own failures, fixed before publishing:** a Blade `@if` glued to text rendered as text, and English in the RGESN's titles (Power Usage Effectiveness…), now `lang="en"` (8.7). The tests caught a third: the statements loaded Livewire's script (84 KB) without a component; the layout now leaves it out (RGESN 6.5).
- **RGESN 2024: 37 satisfied, 14 not, 27 not applicable, so 72 %** (rounded down, like the RGAA rate). Not satisfied: the supported devices and browsers (2.1, 2.2, 2.4), decommissioning (2.7), suppliers (2.8), the basemap loaded without asking (4.4), a total page weight and request budget (6.1), and the host's environmental figures (8.1 to 8.7), left undocumented by the maintainer (2026-10-24). Each says what is planned. Its justifications are in French, since the statement publishes them; the RGAA audit's notes stay in English, as evidence for reviewers.
- **Report link in the footer**, on the right, at the maintainer's request: the header keeps the title, the selectors and the buttons.
- **12.1, two navigation systems: not applicable.** The RGAA's particular case for a very limited number of pages all reachable from the home page: the map and the two statements.
