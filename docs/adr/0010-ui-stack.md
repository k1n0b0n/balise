# ADR-0010: UI stack

- **Status:** Proposed
- **Date:** 2026-10-15

## Context

- The UI is one interactive page plus a few static pages (accessibility and ecodesign statements, E1.5). No forms, no accounts.
- The interactive page holds client-side state shared by several parts: the map (E3.1, E3.2), the vehicle popup (E3.3), the selected line or vehicle that the map follows (E3.4), the text view equivalent to the map (E3.5) and the pause control (E3.6).
- Positions are pushed by Reverb (E4.1): about 400 vehicles, only changed positions sent (ADR-0006).
- RGAA 4.1.2 AA and RGESN 2024 apply to every option (ADR-0006).
- `architecture.md` listed Livewire as "agreed" without an ADR; E1.1 shipped a Livewire home component.
- Audience: Laravel reviewers (brief). The maintainer's front-end experience is Vue 3 with TypeScript.

### Measured weights (minified + gzip, 2026-10-15)

| Library | Size |
|---|---|
| MapLibre GL JS 6.14 (main + worker) | ~430 KB |
| Livewire 4.4 (Alpine included) | ~86 KB |
| Vue 3.5 runtime | ~42 KB |
| Inertia 3.9 (core + Vue adapter) | ~38 KB |

MapLibre dominates every option; the framework is 15 to 20 % of the map page's JavaScript.

## Options compared

| Criterion | A. Livewire + Alpine, MapLibre island | B. Inertia + Vue 3 + TypeScript | C. Nuxt front + Laravel API |
|---|---|---|---|
| Framework weight | ~86 KB | ~80 KB | Not measured; Vue plus the Nuxt runtime, more than B |
| First render | Server HTML, text view readable before JS | Client render, unless Inertia SSR (a Node process) | Server HTML (Nuxt SSR, a Node process) |
| Live text view | Each push makes every client call the server to re-render, or the live part moves to Alpine | Rendered in the browser from the pushed payload, no extra request | Same as B |
| State shared by map, popup, selection, text view | Split between the server component and the JS island, synchronised by events | One reactive store, typed | Same as B |
| RGAA | Server HTML; focus after updates handled by hand | Focus and live regions handled by hand | `NuxtRouteAnnouncer` helps on navigation, the rest by hand |
| Quality gates to add | None | `vue-tsc`, ESLint, Vitest | Same as B, plus API contract tests |
| Repository and hosting | One Laravel app, as today | One Laravel app, Vite builds the Vue pages | Two apps (`api/`, `web/`), two deploys, a Node daemon on Forge |
| Readability for a Laravel reviewer | First-party starter kit | First-party starter kit | Laravel reduced to an API |
| Maintainer's skills | New to Livewire 4 | Strong in Vue 3 + TS; Inertia to learn | Nuxt to relearn |

## Decision

To be taken by the maintainer. Recommendation: **B, Inertia + Vue 3 + TypeScript.**

1. The map page is a Vue app served through Inertia: map, popup, selection, text view and pause share one typed store fed by Reverb (Laravel Echo).
2. Static pages (E1.5 statements) stay plain Blade, without JavaScript.
3. No SSR for the MVP. The map page needs JavaScript anyway; the text view is reachable by keyboard and screen reader once loaded. SSR gets its own ADR if an audit shows the need.
4. Quality gates grow: `vue-tsc --noEmit`, ESLint, Vitest for composables, all behind one npm script run by CI next to `composer qa`.

## Alternatives considered

| Option | Why not |
|---|---|
| A. Livewire + Alpine | Best first render and no new gates, but the page is mostly client state pushed in real time: either every push costs a server round trip per client, or the live logic moves to Alpine strings that are hard to type and test |
| C. Nuxt + Laravel API | Two apps, two deploys and a Node server for one interactive page; Laravel becomes an API, which hides the fullstack Laravel work the project is meant to show. Nuxt practice fits a separate side project better |
| D. Blade pages + a bare Vue island (no Inertia) | Lightest (~42 KB), but initial data needs a JSON endpoint or data attributes; Inertia gives typed controller props and the conventional Laravel + Vue layout for ~38 KB |

## Consequences

- Livewire is removed; the E1.1 home component becomes an Inertia page, and `AGENTS.md`, `architecture.md` and the Boost guidelines are updated.
- E3.1 in the PRD becomes "MapLibre map in a Vue page served by Inertia".
- One more toolchain (TypeScript, ESLint, Vitest) to keep up to date; Dependabot covers it.
- Without SSR, the text view needs JavaScript; this is acceptable for RGAA but is stated in the accessibility statement (E1.5).
- The live text view renders without extra requests, which helps the RGESN budget and the load test (E5.3).
