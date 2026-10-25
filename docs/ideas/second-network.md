# Idea: which second network

- **Status:** Exploring; E6 comes after M3 (decided 2026-10-26)
- **Related:** ADR-0001, E6.1, [next-departures.md](next-departures.md)

## Grenoble (TAG, checked 2026-10-25)

- **Static GTFS**: available and current (`data.mobilites-m.fr/api/gtfs/SEM`, 5.2 MB, valid until 2026-12-31): 2,527 stops with their stations, 54 lines with colours, shapes, stop times. Licence ODbL, which asks for attribution and share-alike on derived databases, unlike TBM's Licence Ouverte: it matters for public recordings (ADR-0022).
- **No vehicle positions**: transport.data.gouv.fr lists only trip updates for TAG, dating from 2019; their URL (`data.metromobilite.fr/api/gtfs-rt/GAM/trip-update`) is refused by the producer's Cloudflare from the workstation and from the server (`403`), and the new domain answers `404` on the paths tried. The producer's contact: `contact@mobilites-m.fr`.
- **So**: Grenoble cannot show vehicles; it could show stations and, if its trip updates can be read, next departures (E8).

## Networks with GTFS-RT vehicle positions on transport.data.gouv.fr (2026-10-25)

142 datasets; among large cities: STAR (Rennes, metro and bus), TaM (Montpellier, tram), Lignes d'Azur (Nice), DiviaMobilités (Dijon), Fil Bleu (Tours), Mistral (Toulon), Irigo (Angers). To check before E6: the feed's freshness and content, the licence, and whether trip updates are published too (for E8).
