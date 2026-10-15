# ADR-0008: CSS approach, Tailwind CSS

- **Status:** Accepted
- **Date:** 2026-10-15

## Context

- The first real interface arrives with E3.1 (map page), then the text view (E3.5) and the public statements (E1.5).
- The skeleton shipped Tailwind; it was removed in E1.1 because no decision covered it.
- Constraints from ADR-0006: RGAA AA (visible focus, contrast, no information by colour alone) and RGESN (small pages, few requests).
- MapLibre ships its own stylesheet for its controls and popups.
- Audience: technical reviewers, many of them from the Laravel ecosystem.

## Decision

1. **Tailwind CSS (v4, through its Vite plugin)** is the CSS approach, **installed with E3.1**, not before.
2. **Design tokens** (colours, spacing, line colours mapping) live in the Tailwind theme, in CSS, not scattered in templates.
3. **Accessibility is our job, not the framework's:** focus styles are defined explicitly (`focus-visible`), colour pairs are checked for contrast, and repeated class groups become Blade components rather than copy-paste.
4. **MapLibre keeps its own stylesheet**; E3.1 checks that Tailwind's base reset does not break the map controls.
5. **No web font for now:** system font stack. A brand font is a PRD *Later* item, decided with a visual identity.

## Alternatives considered

| Option | Why not |
|---|---|
| Plain modern CSS (custom properties, nesting, cascade layers) | No dependency and fully standard, but every utility and naming convention is ours to design and keep consistent; slower for a solo maintainer |
| Classless framework (Pico CSS) | Very light and accessible defaults, but limited once the layout goes beyond documents (map, side panel, overlays) |
| Component framework (Bootstrap) | Ready-made components, but heavier CSS and JavaScript, generic look, less common in current Laravel projects |
| UnoCSS | Same utility approach, faster engine, but smaller community and less familiar to Laravel reviewers |

## Consequences

- Only the classes actually used are generated: the CSS stays small (RGESN).
- Templates carry many utility classes; Blade components keep them readable.
- One more front-end dependency, added in E3.1 with its own commit.
