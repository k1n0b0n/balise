# Idea: pushing positions to the page

- **Status:** Studied, left for later (2026-10-24)
- **Replaced by:** E4.1 (polling aligned on the feed's versions) and E4.2 (answers cached in nginx)

## Facts

- TBM publishes every 20 s at a fixed second (:02, :22, :42), served a second later (story 024): nothing on screen can be fresher than that.
- Before E4.1 the page asked every 5 s; three answers in four were bodyless `304`s, and the page showed a version 2.5 s after it was recorded on average.
- Every request went through Laravel, PHP-FPM included, even for a `304`. A delta at midday weighs about 12 KB gzipped, 40 to 60 KB uncompressed.
- PHP-FPM's pool runs the official image's default, `pm.max_children = 5`.

## Options

| | Option | Requests per visitor | Added delay | What it adds | Server cost |
|---|---|---|---|---|---|
| A | Polling every 5 s with `ETag` (before E4.1) | 720/h | 2.5 s on average | nothing | 720 trips through Laravel per hour |
| B | Polling aligned on the next version (E4.1) | ~180/h | under 1 s | a header and the timing | a quarter |
| C | Answers cached in nginx until the next version (E4.2) | as B | as B | a cache in the web image | about one PHP request per version |
| D | Reverb, pushing a signal, then the page fetches the delta | ~180/h and a connection | ~0 | Reverb, Echo, pusher-js, a service, the host's nginx (`Upgrade`) | a connection per visitor, and every page asking at once on each signal |
| E | Reverb pushing the delta itself | a message per version | ~0 | as D, and a message size above Reverb's 10 KB | 40 to 60 KB uncompressed per message and visitor |
| F | Server-Sent Events, through a hub (Mercure) | a stream | ~0 | a hub service, a JWT to publish | a connection per visitor |

Long polling and SSE served by Laravel itself would hold one of five PHP-FPM workers per visitor: five visitors would take the site down.

## For and against pushing (D, F)

- **For:** what the PRD's M2 first promised; the freshest possible page; nothing sent between versions, which suits a phone's battery. F needs no JavaScript library (`EventSource`), reconnects and resumes on its own (`Last-Event-ID`, the change log's versions), and probably needs no change to the host's nginx; D is the Laravel way.
- **Against:** the gain is about 2 s over B, since the feed changes every 20 s; a service, a connection per visitor, mobile networks that drop it, and a fallback to polling that never goes away; D asks the host's nginx for `Upgrade` and three dependencies; on RGESN grounds a permanent connection and its pings are no clear win over a `304` every 20 s.

## Decision

B and C first, which every push option would need anyway as its fallback and to absorb the requests a signal sets off. Pushing goes to the PRD's *Later*; if it comes back, F before D, with an ADR.
