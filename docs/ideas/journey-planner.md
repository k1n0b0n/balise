# Idea: a journey from one address to another

- **Status:** Exploring (2026-10-26); the maintainer's order: M4 (a second network) first, built with the planner in mind, then the planner
- **Related:** the brief's scope (timetables and trip planning out of the MVP), ADR-0001 (ports), E8.1 (trip updates port, story 034), E8.2 (departures, story 035), [next-departures.md](next-departures.md), [network-on-map.md](network-on-map.md)

## Why

The maintainer asked on 2026-10-26: from one address to another, combine the network's lines to shorten the journey, its number of changes, or both. The map shows where vehicles are and, since E8.2, when they leave a stop; a journey is the next question a rider asks.

## Where it leads

The maintainer's goal (2026-10-26) goes beyond one city:

1. **First, one city without trains**: the network's trams, buses and boats, from one address to another. This study is about this step.
2. **Then from one city to another**: trains (TER, Intercités, TGV) and the buses of other cities, for a whole journey across a region or the country.
3. **An alternative: by bike with transit**: riding to a station, taking the bike on a train, a tram or a boat, never on a bus, and riding on.

What the later steps change in the first one's decisions, so that it does not close their way:

| Later need | Consequence for the first step |
|---|---|
| Several networks in one search (step 2) | Routing works on any number of networks: ids are already namespaced per network (ADR-0001), and walks between platforms are found by distance, whatever network each belongs to. A second network (M4) becomes a step towards the planner rather than a detour |
| Trains, planned days ahead (step 2) | Trip updates only cover the coming hours: trains need the static timetables, with their calendars. The routing reads its timetable through an interface, so that "static timetables corrected by trip updates" can replace "trip updates only" without touching RAPTOR (decision 2) |
| A region or a country (step 2) | Tens of thousands of stops and millions of stop times: RAPTOR in PHP, rebuilt each minute, would not hold. Decision 6 then leans towards an engine built for that scale (MOTIS, OpenTripPlanner), or a compiled timetable kept between requests |
| Bikes on board (step 3) | GTFS says per trip whether bikes are allowed (`bikes_allowed`). TBM sets it only on its boats (1,556 trips allowed); its trams and buses say nothing (66,978 trips at "no information"), so a rule per mode in the network's configuration fills the gap. Riding needs a real cycling router on OpenStreetMap: the straight line will not do for a bike (decision 5) |

## What it takes

| Building block | What balise has | What it would need |
|---|---|---|
| An address to coordinates | Nothing | A geocoder: France's national address base (BAN), through IGN's Géoplateforme |
| Walking: to the first stop, between stops, from the last stop | The coordinates of the 5,235 platforms | Straight-line distance with a detour factor, or a pedestrian router on OpenStreetMap |
| The journey on the network | The trip updates (E8.1): every timed stop of the coming hours, delays included | A routing algorithm and the timetable laid out for it |
| The answer | The map, popups, the text view, the station search | A form with two addresses, a list of journeys, the chosen one drawn on the map |

## Measures (2026-10-26)

| Measure | Value |
|---|---|
| `api-adresse.data.gouv.fr` | Still answers, but its headers announce `Deprecation` and `Sunset` on 2026-01-31 |
| Géoplateforme (`data.geopf.fr/geocodage/search`) | 0.10 to 0.15 s, no key, `ratelimit-limit: 50` per second and address; `autocomplete=1`; the `poi` index finds "Gare Saint-Jean" as a tram station, the address index only streets |
| Trip updates as a timetable, 21:06 | 740 trips, 24,144 timed stop times, the last one 4.7 h ahead |
| The same, 15:14 (story 034) | 1,759 trips, 59,937 timed stop times; up to 9 h ahead at midday ([next-departures.md](next-departures.md)); about 300 KB at night, nearly empty |
| Platforms within 300 m of each other | 36,736 pairs, the candidate walks between stops |
| A RAPTOR prototype (pure PHP, thrown away, outside the repository) | 228 routes and the walks built in 0.14 s; a search of 5 rounds in 3 to 4 ms, from the 34 to 63 platforms within 600 m of each address; 77 MB peak memory, decoding included |
| Its answers at 21:06 | 20 cours Pasteur → Gare Saint-Jean: 19 min without a change, 18 min with one; → Mérignac Centre: 31 min; Pessac Centre → Lormont: 121 min with one change, which looks wrong: a bug of the prototype or the evening's service, to check against TBM's own planner before trusting any figure |

## Decisions to weigh

Each decision lists its options with what speaks for and against them, then the strongest case against the option preferred.

### 1. The brief's scope

| Option | For | Against |
|---|---|---|
| **Keep trip planning out** | balise stays a live map with its departures, readable in ten minutes | The rider's next question stays unanswered |
| **A live planner: leaving now, within the coming hours** | Built on the trip updates already read (E8.1): real time by construction, no new feed | Nothing at night, nothing for tomorrow; one more epic before a second network (M4) |
| **A full planner: any day, any time** | What riders expect of a planner | Static stop times and calendars (2.6 million rows a day for TBM), a second source to reconcile with the live one: another product |

Preferred: a live planner, after M4 or instead of it, as the maintainer chooses. **Against it:** TBM's app and general-purpose maps already plan journeys with full timetables and real footpaths; what balise would add is a readable implementation, not a better service.

### 2. The timetable

| Option | For | Against |
|---|---|---|
| **Trip updates only** | Already read and cached a minute (E8.1); delays included; trips grouped into routes in 0.14 s | Its horizon shrinks in the evening (4.7 h at 21:06) and vanishes at night; a trip not yet in the feed does not exist |
| **Static stop times and calendars** | Any time, any day | 193 MB of `stop_times.txt` a day, service calendars and their exceptions, then live corrections on top |
| **Both** | The planner's standard design | Both costs, and trips to match between the two (`nt_` trips exist only in the live feed) |

Preferred: trip updates only, with the page saying how far ahead it can plan. **Against it:** a planner that answers "no data" at one in the morning, or for a trip at 7:00 asked at 22:00, reads as broken.

### 3. The algorithm

| Option | For | Against |
|---|---|---|
| **RAPTOR** | Works in rounds, one ride per round: round 1 finds the journeys without a change, round 2 with one, and so on. It answers "fastest" and "fewest changes" together, as the set of journeys where none is both slower and has more changes; the prototype answered in milliseconds | Routes must be built from trips with the same stops, rebuilt with each read of the feed |
| **Connection Scan (CSA)** | The simplest: one pass over the stop times in time order | Gives the earliest arrival only; counting changes needs its profile variant, harder to read |
| **McRAPTOR** (more criteria: walking time, fares) | Richer choices | Slower and harder to explain, for criteria nobody asked for |

Preferred: RAPTOR. **Against it:** its correctness is easy to get subtly wrong (the prototype's 121 min); it needs tests on handmade networks whose answers are known, before any live figure.

### 4. Geocoding

| Option | For | Against |
|---|---|---|
| **Géoplateforme, called by the server** | Official, free, no key, addresses and places; one dependency behind a port, its answers cacheable | An external service (an ADR); 50 requests a second per address, and every visitor would share the server's |
| **Géoplateforme, called by the browser** | Each visitor has their own limit; no load on the server | The typed address leaves for a third party from the visitor's browser; the page then depends on two origins |
| **BAN self-hosted** (addok) | No dependency at run time | Gigabytes of data and a search service to run, for a demonstration |
| **Stations only** (the E7.1 search) | Nothing new | Not what was asked: riders think in addresses |

Preferred: Géoplateforme called by the server, behind a port like the feeds. **Against it:** a planner is only as good as its first step; a slow or failing geocoder makes the whole feature fail, so the stations search must remain the fallback.

### 5. Walking

| Option | For | Against |
|---|---|---|
| **Straight line × 1.3, at 4.5 km/h** | No data, no service; the prototype's 36,736 pairs in a fraction of a second | Wrong where the straight line crosses what cannot be crossed: the Garonne has a few bridges, railways and ring roads have few crossings |
| **A pedestrian router on OpenStreetMap** (Valhalla, OSRM, GraphHopper) | Real paths and times | A second service of about a gigabyte for the Gironde, to run and update |
| **An external routing API** | Real paths, nothing to run | A second dependency, with a key and limits |

Preferred: the straight line, refusing walks that cross the river (a line between the two banks, checked once per pair). **Against it:** every other barrier stays invisible, and a rider sent to a stop behind a railway loses trust in every answer.

### 6. Build it or use an engine

| Option | For | Against |
|---|---|---|
| **In balise, in plain Laravel** | The same code style as the rest, readable by reviewers, on data already read | Several stories, and results to prove against a reference |
| **OpenTripPlanner or MOTIS beside it** | Complete planners, GTFS and GTFS-Realtime, real footpaths | A Java or C++ service of several gigabytes on a shared server; balise would only display its answers |
| **Navitia's API** | Covers Bordeaux already | A key, an account, and the core of the feature outside the repository |

Preferred: in balise. **Against it:** an engine would answer better from the first day; building one is justified only by what the repository is for, showing readable code. Steps 2 and 3 strengthen this case: at a country's scale, with bikes, an engine is the realistic choice, and the city-scale code would mostly be a prototype.

## Interface

- **Form:** "Départ" and "Arrivée", each an address field with suggestions (an accessible combobox, RGAA 7 and 11), a "Partir maintenant" choice only, since the feed covers the coming hours; a station from the search accepted too.
- **Results:** two or three journeys, the fastest and the one with the fewest changes when they differ, as text first: "4 min à pied jusqu'à Victoire; Tram B vers Pessac Centre à 21:12, 6 arrêts; 3 min à pied". The text is the RGAA equivalent of the drawing.
- **Map:** the chosen journey drawn over the network, its legs in their lines' colours, walking dashed.

## If it goes into the PRD

An epic of four or five stories, in this order:

1. An ADR: geocoding behind a port, the walking rule, the scope (live planner only).
2. The timetable for routing: the trip updates' trips grouped into routes, kept with each read (the E8.1 cache), and the walks between platforms.
3. RAPTOR, tested on handmade networks whose answers are known, then against TBM's planner on a handful of journeys.
4. The form and the journeys, rendered by the server; the geocoder's suggestions.
5. The journey on the map.

## Open questions

- Why the prototype gave 121 min from Pessac Centre to Lormont: compare five journeys with TBM's planner at the same time.
- The horizon of the trip updates hour by hour, over a whole day: they are not recorded (ADR-0022), so a day of reads is needed to know when the planner can answer.
- How many of the 36,736 walks cross the Garonne or a railway.
- Whether "Ma position" (the browser's geolocation) is wanted as a starting point, and what the RGAA and privacy statements then say.
