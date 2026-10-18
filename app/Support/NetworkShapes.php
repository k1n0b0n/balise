<?php

declare(strict_types=1);

namespace App\Support;

use App\Ingestion\CatalogShape;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The lines of a network drawn on the map (ADR-0016): one GeoJSON file per network, one feature per line, derived from
 * the catalogue and written only when its content changes, so that browsers keep their cached copy.
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
     * Points of a shape are a few metres apart (median 6 m on TBM, 343 m at the 99.9th percentile); a longer step is a
     * fault of the source, such as the depot runs of TBM's line G zigzagging across the city, and is left as a gap.
     */
    public const float MAX_STEP_METRES = 500.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * Writes the network's file when its shapes or their lines changed.
     *
     * @param  iterable<CatalogShape>  $shapes
     * @param  array<string>  $lineIds  the lines of the catalogue: a shape of an unknown line is left out
     * @return string the fingerprint of the file, also its ETag
     */
    public function store(string $network, iterable $shapes, array $lineIds, ?string $previousFingerprint): string
    {
        $json = $this->geoJson($shapes, $lineIds);
        $fingerprint = hash('xxh128', $json);

        if ($fingerprint !== $previousFingerprint || ! $this->disk()->exists($this->path($network))) {
            // Written aside then moved, so that the endpoint never serves half a file.
            $this->disk()->put($this->path($network).'.tmp', $json);
            $this->disk()->move($this->path($network).'.tmp', $this->path($network));
        }

        return $fingerprint;
    }

    public function read(string $network): ?string
    {
        return $this->disk()->get($this->path($network));
    }

    private function path(string $network): string
    {
        return "shapes/{$network}.json";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
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
            if ($this->distance($points[$index - 1], $points[$index], $scaleX) > self::MAX_STEP_METRES) {
                $runs[] = [];
            }
            $runs[array_key_last($runs)][] = $points[$index];
        }

        return $runs;
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
