<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ingestion\CatalogLine;
use App\Ingestion\CatalogStop;
use App\Ingestion\NetworkCatalogs;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\Stop;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ImportNetworkCatalog
{
    private const int CHUNK = 1000;

    public function __construct(private NetworkCatalogs $catalogs) {}

    /**
     * Replace a network's lines and stops with its current catalogue.
     *
     * @return array{lines: int, stops: int}|null null when the catalogue has not changed
     */
    public function handle(string $network): ?array
    {
        $previous = CatalogImport::query()->find($network);
        $snapshot = $this->catalogs->for($network)->fetch($previous?->etag);

        if ($snapshot === null) {
            return null;
        }

        // Rows not touched by this run belong to lines or stops that left the feed.
        $syncId = (string) Str::uuid();

        return DB::transaction(function () use ($network, $snapshot, $syncId): array {
            $lines = $this->upsert(Line::class, $snapshot->lines(), fn (CatalogLine $line): array => [
                'id' => $line->id,
                'network' => $network,
                'short_name' => $line->shortName,
                'long_name' => $line->longName,
                'mode' => $line->mode->value,
                'color' => $line->color,
                'text_color' => $line->textColor,
                'sync_id' => $syncId,
            ]);

            $stops = $this->upsert(Stop::class, $snapshot->stops(), fn (CatalogStop $stop): array => [
                'id' => $stop->id,
                'network' => $network,
                'name' => $stop->name,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'sync_id' => $syncId,
            ]);

            Line::query()->where('network', $network)->where('sync_id', '!=', $syncId)->delete();
            Stop::query()->where('network', $network)->where('sync_id', '!=', $syncId)->delete();

            CatalogImport::query()->updateOrCreate(
                ['network' => $network],
                ['etag' => $snapshot->etag(), 'imported_at' => now()],
            );

            return ['lines' => $lines, 'stops' => $stops];
        });
    }

    /**
     * @template TItem of object
     *
     * @param  class-string<Model>  $model
     * @param  iterable<TItem>  $items
     * @param  Closure(TItem): array<string, mixed>  $toRow
     */
    private function upsert(string $model, iterable $items, Closure $toRow): int
    {
        $count = 0;
        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [...$toRow($item), 'created_at' => $now, 'updated_at' => $now];

            if (count($rows) === self::CHUNK) {
                $count += $this->write($model, $rows);
                $rows = [];
            }
        }

        return $count + $this->write($model, $rows);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function write(string $model, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $model::query()->upsert($rows, ['id'], array_values(array_diff(array_keys($rows[0]), ['id', 'created_at'])));

        return count($rows);
    }
}
