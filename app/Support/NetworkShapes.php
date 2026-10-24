<?php

declare(strict_types=1);

namespace App\Support;

use App\Ingestion\CatalogShape;
use App\Models\CatalogImport;

/**
 * The lines of a network drawn on the map (ADR-0016): one GeoJSON document per network, one feature per line, derived
 * from the catalogue and written only when its content changes, so that browsers keep their cached copy. It is kept in
 * the network's import row, where every container reads the same one; a file under storage/ was each container's own.
 */
final readonly class NetworkShapes
{
    /**
     * Points closer than this to the simplified line are dropped; chosen at street and city zoom levels (E3.8).
     */
    public const float TOLERANCE_METRES = 11.0;

    /**
     * Five decimals are about a metre, well under the tolerance.
     */
    private const int DECIMALS = 5;

    /**
     * Points of a shape are a few metres apart (median 6 m on TBM, 343 m at the 99.9th percentile). A longer step that
     * goes straight on is a sparse but true stretch (a bridge, a straight road: TBM's line 39); one that turns sharply
     * is a fault of the source, such as the depot runs of TBM's line G zigzagging across the city, and is left as a gap.
     */
    public const float MAX_STEP_METRES = 500.0;

    public const float MAX_TURN_DEGREES = 30.0;

    /**
     * The direction before and after a step is taken over at least this distance, so that a doubled point is ignored.
     */
    private const float TURN_REACH_METRES = 30.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * Writes the network's shapes when they or their lines changed, or when none are stored; its import row must exist.
     *
     * @param  iterable<CatalogShape>  $shapes
     * @param  array<string>  $lineIds  the lines of the catalogue: a shape of an unknown line is left out
     * @return string the fingerprint of the shapes, also their ETag
     */
    public function store(string $network, iterable $shapes, array $lineIds, ?string $previousFingerprint): string
    {
        $json = $this->geoJson($shapes, $lineIds);
        $fingerprint = hash('xxh128', $json);

        if ($fingerprint !== $previousFingerprint || ! $this->has($network)) {
            CatalogImport::query()->whereKey($network)->update(['shapes' => $json]);
        }

        return $fingerprint;
    }

    public function has(string $network): bool
    {
        return CatalogImport::query()->whereKey($network)->whereNotNull('shapes')->exists();
    }

    public function read(string $network): ?string
    {
        $json = CatalogImport::query()->whereKey($network)->value('shapes');

        return is_string($json) ? $json : null;
    }

    /**
     * @param  iterable<CatalogShape>  $shapes
     * @param  array<string>  $lineIds
     */
    private function geoJson(iterable $shapes, array $lineIds): string
    {
        $known = array_flip($lineIds);
        $paths = [];

        foreach ($shapes as $shape) {
            foreach ($this->split($shape->points) as $points) {
                $path = $this->simplify($points);

                if (count($path) < 2) {
                    continue;
                }

                // Keyed by content: a line whose trips share a shape, or two shapes that simplify alike, draws it once.
                $key = hash('xxh128', (string) json_encode($path));

                foreach ($shape->lineIds as $lineId) {
                    if (isset($known[$lineId])) {
                        $paths[$lineId][$key] = $path;
                    }
                }
            }
        }

        ksort($paths, SORT_STRING);
        $features = [];

        foreach ($paths as $lineId => $linePaths) {
            ksort($linePaths, SORT_STRING);
            $features[] = [
                'type' => 'Feature',
                'properties' => ['line' => $lineId],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => array_values($linePaths)],
            ];
        }

        return (string) json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR);
    }

    /**
     * Cuts the shape where two points are too far apart to be on the same street.
     *
     * @param  list<array{float, float}>  $points
     * @return list<list<array{float, float}>>
     */
    private function split(array $points): array
    {
        if ($points === []) {
            return [];
        }

        $scaleX = self::METRES_PER_DEGREE * cos(deg2rad($points[0][1]));
        $runs = [[$points[0]]];

        for ($index = 1; $index < count($points); $index++) {
            if ($this->distance($points[$index - 1], $points[$index], $scaleX) > self::MAX_STEP_METRES && $this->turnsSharply($points, $index, $scaleX)) {
                $runs[] = [];
            }
            $runs[array_key_last($runs)][] = $points[$index];
        }

        return $runs;
    }

    /**
     * Whether the path turns by more than the limit as it enters or leaves the step that ends at `$index`.
     *
     * @param  list<array{float, float}>  $points
     */
    private function turnsSharply(array $points, int $index, float $scaleX): bool
    {
        [$from, $to] = [$points[$index - 1], $points[$index]];
        $step = $this->bearing($from, $to, $scaleX);

        for ($before = $index - 2; $before >= 0; $before--) {
            if ($this->distance($points[$before], $from, $scaleX) >= self::TURN_REACH_METRES) {
                if ($this->turn($this->bearing($points[$before], $from, $scaleX), $step) > self::MAX_TURN_DEGREES) {
                    return true;
                }
                break;
            }
        }

        for ($after = $index + 1; $after < count($points); $after++) {
            if ($this->distance($to, $points[$after], $scaleX) >= self::TURN_REACH_METRES) {
                return $this->turn($step, $this->bearing($to, $points[$after], $scaleX)) > self::MAX_TURN_DEGREES;
            }
        }

        return false;
    }

    /**
     * @param  array{float, float}  $from
     * @param  array{float, float}  $to
     */
    private function bearing(array $from, array $to, float $scaleX): float
    {
        return rad2deg(atan2(($to[1] - $from[1]) * self::METRES_PER_DEGREE, ($to[0] - $from[0]) * $scaleX));
    }

    private function turn(float $from, float $to): float
    {
        $angle = fmod(abs($from - $to), 360.0);

        return min($angle, 360.0 - $angle);
    }

    /**
     * Douglas-Peucker on a local flat projection, precise enough at the scale of a city.
     *
     * @param  list<array{float, float}>  $points  longitude and latitude
     * @return list<array{float, float}>
     */
    private function simplify(array $points): array
    {
        $last = count($points) - 1;

        if ($last < 1) {
            return [];
        }

        $scaleX = self::METRES_PER_DEGREE * cos(deg2rad($points[0][1]));
        $kept = [0 => true, $last => true];
        $ranges = [[0, $last]];

        while (($range = array_pop($ranges)) !== null) {
            [$first, $end] = $range;
            [$farthest, $distance] = [null, self::TOLERANCE_METRES];

            for ($index = $first + 1; $index < $end; $index++) {
                $offset = $this->distanceToSegment($points[$index], $points[$first], $points[$end], $scaleX);

                if ($offset > $distance) {
                    [$farthest, $distance] = [$index, $offset];
                }
            }

            if ($farthest !== null) {
                $kept[$farthest] = true;
                $ranges[] = [$first, $farthest];
                $ranges[] = [$farthest, $end];
            }
        }

        ksort($kept);
        $path = [];

        foreach (array_keys($kept) as $index) {
            $point = [round($points[$index][0], self::DECIMALS), round($points[$index][1], self::DECIMALS)];

            if ($path === [] || $path[array_key_last($path)] !== $point) {
                $path[] = $point;
            }
        }

        return $path;
    }

    /**
     * @param  array{float, float}  $point
     * @param  array{float, float}  $start
     * @param  array{float, float}  $end
     */
    private function distanceToSegment(array $point, array $start, array $end, float $scaleX): float
    {
        [$x, $y] = [($point[0] - $start[0]) * $scaleX, ($point[1] - $start[1]) * self::METRES_PER_DEGREE];
        [$dx, $dy] = [($end[0] - $start[0]) * $scaleX, ($end[1] - $start[1]) * self::METRES_PER_DEGREE];
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared === 0.0 ? 0.0 : max(0.0, min(1.0, ($x * $dx + $y * $dy) / $lengthSquared));

        return hypot($x - $t * $dx, $y - $t * $dy);
    }

    /**
     * @param  array{float, float}  $from
     * @param  array{float, float}  $to
     */
    private function distance(array $from, array $to, float $scaleX): float
    {
        return hypot(($to[0] - $from[0]) * $scaleX, ($to[1] - $from[1]) * self::METRES_PER_DEGREE);
    }
}
