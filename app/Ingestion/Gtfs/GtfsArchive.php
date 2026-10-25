<?php

declare(strict_types=1);

namespace App\Ingestion\Gtfs;

use App\Enums\TransportMode;
use App\Ingestion\CatalogLine;
use App\Ingestion\CatalogShape;
use App\Ingestion\CatalogSnapshot;
use App\Ingestion\CatalogStop;
use Generator;
use RuntimeException;
use ZipArchive;

/**
 * A downloaded GTFS archive, read file by file and row by row so that memory stays flat.
 */
final class GtfsArchive implements CatalogSnapshot
{
    public function __construct(
        private readonly string $network,
        private readonly string $path,
        private readonly ?string $etag,
    ) {}

    public function __destruct()
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function etag(): ?string
    {
        return $this->etag;
    }

    public function copyTo(string $path): void
    {
        if (! copy($this->path, $path)) {
            throw new RuntimeException("Cannot copy the GTFS archive to {$path}.");
        }
    }

    /**
     * @return Generator<CatalogLine>
     */
    public function lines(): Generator
    {
        foreach ($this->rows('routes.txt') as $route) {
            yield new CatalogLine(
                id: $this->id($route['route_id']),
                shortName: $route['route_short_name'] ?: ($route['route_long_name'] ?? $route['route_id']),
                longName: ($route['route_long_name'] ?? '') ?: null,
                mode: $this->mode((int) $route['route_type']),
                color: $this->color($route['route_color'] ?? null),
                textColor: $this->color($route['route_text_color'] ?? null),
            );
        }
    }

    /**
     * Stops and platforms only: stations, entrances and other nodes carry no vehicle.
     *
     * @return Generator<CatalogStop>
     */
    public function stops(): Generator
    {
        foreach ($this->rows('stops.txt') as $stop) {
            if (! in_array($stop['location_type'] ?? '', ['', '0'], true)) {
                continue;
            }

            yield new CatalogStop(
                id: $this->id($stop['stop_id']),
                name: $stop['stop_name'],
                latitude: (float) $stop['stop_lat'],
                longitude: (float) $stop['stop_lon'],
            );
        }
    }

    /**
     * GTFS ties shapes to lines only through trips; a shape no trip follows is left out. Points are kept as flat lists
     * of numbers while reading: hundreds of thousands of small arrays would take several times the memory.
     *
     * @return Generator<CatalogShape>
     */
    public function shapes(): Generator
    {
        if (! $this->has('shapes.txt')) {
            return;
        }

        $lines = [];

        foreach ($this->rows('trips.txt') as $trip) {
            if (($trip['shape_id'] ?? '') !== '') {
                $lines[$trip['shape_id']][$this->id($trip['route_id'])] = true;
            }
        }

        $sequences = [];
        $coordinates = [];

        foreach ($this->rows('shapes.txt') as $point) {
            $shapeId = $point['shape_id'];

            if (isset($lines[$shapeId])) {
                $sequences[$shapeId][] = (int) $point['shape_pt_sequence'];
                $coordinates[$shapeId][] = (float) $point['shape_pt_lon'];
                $coordinates[$shapeId][] = (float) $point['shape_pt_lat'];
            }
        }

        foreach ($sequences as $shapeId => $order) {
            asort($order);
            $points = [];

            foreach (array_keys($order) as $index) {
                $points[] = [$coordinates[$shapeId][2 * $index], $coordinates[$shapeId][2 * $index + 1]];
            }

            yield new CatalogShape(lineIds: array_keys($lines[$shapeId]), points: $points);
        }
    }

    private function has(string $file): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($this->path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Not a readable GTFS archive: {$this->path}.");
        }

        try {
            return $zip->locateName($file) !== false;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return Generator<array<string, string>>
     */
    private function rows(string $file): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($this->path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Not a readable GTFS archive: {$this->path}.");
        }

        $stream = $zip->getStream($file);

        if ($stream === false) {
            throw new RuntimeException("The GTFS archive has no {$file}.");
        }

        try {
            $header = fgetcsv($stream, escape: '');

            if ($header === false) {
                throw new RuntimeException("The GTFS archive has an empty {$file}.");
            }

            $header = array_map(fn (?string $name): string => trim((string) $name, "\u{FEFF} "), $header);

            while (($row = fgetcsv($stream, escape: '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                // Rows may be shorter or longer than the header, e.g. with a trailing comma.
                $row = array_slice(array_pad(array_map(strval(...), $row), count($header), ''), 0, count($header));

                yield array_combine($header, $row);
            }
        } finally {
            fclose($stream);
            $zip->close();
        }
    }

    private function id(string $sourceId): string
    {
        return "{$this->network}:{$sourceId}";
    }

    private function color(?string $hex): ?string
    {
        $hex = strtoupper(trim((string) $hex));

        return preg_match('/^[0-9A-F]{6}$/', $hex) === 1 ? $hex : null;
    }

    /**
     * GTFS route_type, basic values and extended (Hierarchical Vehicle Type) ranges.
     */
    private function mode(int $routeType): TransportMode
    {
        return match (true) {
            $routeType === 0, $routeType >= 900 && $routeType < 1000 => TransportMode::Tram,
            $routeType === 1, $routeType >= 400 && $routeType < 500 => TransportMode::Metro,
            $routeType === 2, $routeType >= 100 && $routeType < 200 => TransportMode::Rail,
            $routeType === 3, $routeType === 11, $routeType >= 200 && $routeType < 300, $routeType >= 700 && $routeType < 900 => TransportMode::Bus,
            $routeType === 4, $routeType >= 1000 && $routeType < 1100, $routeType >= 1200 && $routeType < 1300 => TransportMode::Ferry,
            in_array($routeType, [5, 6, 7], true), $routeType >= 1300 && $routeType < 1500 => TransportMode::CableCar,
            default => TransportMode::Other,
        };
    }
}
