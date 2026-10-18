# Story 011: Every line of the network on the map

- **PRD:** E3.8
- **Status:** Done

## Acceptance criteria

See E3.8 in [prd.md](../prd.md).

## Tasks

- [x] `gtfs` driver: `CatalogSnapshot::shapes()` gives each shape with the lines whose trips follow it (`trips.txt`), points in sequence order; an archive without `shapes.txt` gives none
- [x] `App\Support\NetworkShapes`: cuts a shape where two points are more than 500 m apart, simplifies each shape (Douglas-Peucker), merges a line's shapes into one feature without duplicates, leaves out lines the catalogue lacks, and writes `storage/app/private/shapes/{network}.json` only when its fingerprint changed or the file is missing
- [x] `network:import` writes the file after the lines are committed and stores the fingerprint in `catalog_imports.shapes_fingerprint`
- [x] `GET /api/networks/{network}/shapes`: `no-cache`, the fingerprint as `ETag`, `304` on a revisit, `404` before the first import
- [x] Map: one line layer under the basemap labels and the vehicles, fetched after the first positions, coloured from the line catalogue
- [x] Tests: import (geometry, unchanged shapes not rewritten, lost file rewritten, archive without shapes), endpoint, browser (drawn after the first positions, axe)

## Notes

- **Tolerance: 11 m.** Checked on TBM at city zoom (about 11) and street zoom (about 16): the lines stay on their streets, the two directions of a road show as two close lines.
- **Faulty shapes are cut, not drawn.** Some TBM shapes jump straight across the city: the depot runs of line G (route `901`, "GARE ST-JEAN MECA", 1 to 24 trips each) end with points 0.6 to 1.5 km apart, zigzagging between Nansouty and Saint-Jean. Those points are the stops themselves, visited out of order: `stop_times.txt` gives 1.5 km in 32 s (170 km/h). No other metadata marks them: pickup and drop-off are allowed, and `shape_dist_traveled` follows the zigzag. Points of a shape are 6 m apart at the median and 343 m at the 99.9th percentile, so a step over 500 m is cut and left as a gap (170 steps on 31 lines). Rejected: an implied speed check, which needs the 150 MB `stop_times.txt` and `shape_dist_traveled`, optional in GTFS; dropping rarely used shapes, which would also drop real variants; the "MECA" headsign, specific to TBM.
- **Weight** (TBM, 2026-10-18): 128 lines, 533 paths after cutting, 27,953 points (from 435,442); about 600 KB raw, 95 KB served with gzip. nginx compressed at level 1 by default (120 KB): `gzip_comp_level 6` in `docker/nginx/site.conf`.
- A new archive with the same shapes writes nothing: checked on the real import (file unchanged after a fresh download). The import takes about 18 s and 95 MB at peak; points are kept as flat number lists while reading.
- **Faint lines are opaque, not transparent.** A line has several overlapping shapes (directions, variants), and transparent lines added up where they overlapped, which made them almost solid. Each line is drawn opaque, in its colour mixed at 35 % with Positron's background, which looks transparent and never adds up. E3.4 will draw the selected line with a stronger mix.
- The colours come from the line catalogue through a paint expression: a new catalogue changes the paint, not the data, so the shapes are not rebuilt.
- The shapes are fetched once per page; a failure leaves the map without lines, as before, since they are decorative.
- JavaScript budget: 501.8 KB compressed (+0.4 KB).
- CodeRabbit reviewed the pull request on its own when it was marked ready: one finding, fixed. The archive's ETag was saved before the shapes step, so a failed step, or the first import after deploying this story, left the map without lines until the next archive. The ETag is now saved with the fingerprint, and an import without a fingerprint downloads the archive again.
