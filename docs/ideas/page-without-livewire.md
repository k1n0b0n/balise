# Idea: the map page without Livewire

- **Status:** Exploring
- **From:** story 028 (code review of 2026-10-24, point 15)

## Why

The map page has no Livewire action: Livewire renders it once, then the map module and Alpine do everything. Yet the page loads Livewire's script (84 KB compressed, the third of the map page's JavaScript budget) and starts a session for its CSRF token, a row per visit until story 028 moved sessions to Redis.

## What it could be

A controller rendering the same Blade view, with Alpine loaded on its own (about 15 KB compressed): no session, no cookie, about 70 KB less JavaScript, and a page that a shared cache could keep.

## Against

- ADR-0010 chose Livewire and Alpine for the UI; this would need a new ADR, and a reason to keep Livewire elsewhere (no other page uses it).
- Livewire is part of what the project shows reviewers.

## To decide

Whether the map page is the only Livewire page for good; if so, the ADR.
