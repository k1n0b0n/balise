# ADR-0010: UI stack

- **Status:** Accepted
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
| React 19.3 + React DOM | ~69 KB |
| Svelte 5.57 runtime (before compiled components) | ~15 KB |
| htmx 2.0 | ~17 KB |

MapLibre dominates every option; the framework is 4 to 20 % of the map page's JavaScript.

Alpine is not a separate choice: it ships inside the Livewire bundle and only handles small interactions (toggles, the pause button, mounting the map). The real alternatives to Livewire are another server-driven approach (htmx) or Inertia with a component framework (Vue, React, Svelte).

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

**A. Livewire + Alpine, with the map and the live state in a framework-agnostic TypeScript module.**

1. Pages are Livewire components rendered on the server; Alpine handles small client-side interactions. Static pages (E1.5 statements) are plain Blade.
2. The map, the live positions store and the Reverb subscription live in `resources/js/map/`, written in TypeScript, with no import from Livewire or Alpine. Livewire and Alpine only mount the module (`wire:ignore`) and talk to it through events.
3. TypeScript is type-checked in CI (`tsc --noEmit`), added with E3.1.
4. **Exit rule.** Once E3.5 is done, this decision is reviewed. A new ADR replaces it if either holds:
   - the live text view needs a server round trip per client on every update, and the load test (E5.3) is penalised;
   - live logic spills into Alpine expressions that cannot be typed and tested.
   The new ADR then chooses between two exits: htmx with HTML fragments pushed once to every client (stays server-driven), or Inertia with a component framework (client-side rendering; Vue by default, for the maintainer's skills). Thanks to point 2, either switch rewrites the templates, not the map nor the store.

## Alternatives considered

| Option | Why not |
|---|---|
| B. Inertia + Vue 3 + TypeScript | Fits client state pushed in real time best, but adds `vue-tsc`, ESLint and Vitest before the need is proven. Kept as a possible exit (Decision, point 4); moving from server state to client state is the easier direction |
| Inertia + React | First-party starter kit and the most widespread, but a heavier runtime than Vue for the same role, and less familiar to the maintainer |
| Inertia + Svelte | Compiled, lightest component runtime (good for RGESN), but less common in the Laravel ecosystem and new to the maintainer |
| htmx + Blade fragments | Lightest (~17 KB), progressive enhancement, and can push one rendered fragment to every client instead of a round trip per client. But not first-party: our own conventions for fragment routes and state, no Boost guidelines, less familiar to Laravel reviewers. Kept as a possible exit |
| C. Nuxt + Laravel API | Two apps, two deploys and a Node server for one interactive page; Laravel becomes an API, which hides the fullstack Laravel work the project is meant to show. Nuxt practice fits a separate side project better |
| D. Blade pages + a bare Vue island (no Inertia) | Lightest (~42 KB), but initial data needs a JSON endpoint or data attributes, and it mixes two reactive models in one page |

## Consequences

- The E1.1 Livewire home component stays; no change to the quality gates until E3.1 adds `tsc`.
- First render is server HTML: the text view (E3.5) is readable before JavaScript loads, which serves RGAA and RGESN.
- The map module's API (mount, update positions, select, pause) is designed up front in E3.1; it is the seam for a later switch.
- Learning curve on Livewire 4 for the maintainer; the Boost guidelines and the `livewire-development` skill cover the current API.
