# Project brief

> Status: draft

## Problem

Transit networks publish live vehicle positions as GTFS-Realtime, a binary protobuf feed. Raw, it is unreadable for people and awkward for developers: positions go stale, the feed is polled rather than pushed, and every network adds its own quirks.

## What balise does

Ingest a network's GTFS-RT feed once, clean it, and push live vehicle positions to any number of browsers on an interactive map.

## Audience

1. **Technical reviewers**: the project is a public demonstration of Laravel, real-time and map skills, read in about ten minutes.
2. **Transit users** (later): people who want to see where their bus or tram actually is.

## Vision (long term)

Several networks and cities, each added through configuration rather than code.

## MVP scope

- One network: TBM (Bordeaux).
- Live map of vehicles with their line, last position age and status.
- Public deployment behind a CDN, with an automated pipeline.

## Out of scope for the MVP

- Trip planning and timetables.
- User accounts.
- Mobile apps.
- Networks other than TBM (a second network is the first post-MVP story).

## Known data facts (checked 2026-10-14)

- Static GTFS valid from 2026-10-09 to 2027-07-02, updated daily.
- VehiclePositions feed: 424 positioned vehicles at midday, header 20 s old.
- Some vehicles carry positions up to ~3.5 h old, which must be filtered out.
- The network is nearly empty at night, so the demo needs a replay mode.

## Success criteria

- A reviewer opens the public URL and sees vehicles moving.
- A reviewer opens the repo and understands the architecture from the README and the ADRs.
- Adding a second network does not touch the domain code.
- The service is usable with a screen reader or a keyboard only (RGAA), and sober in data and energy (RGESN).
