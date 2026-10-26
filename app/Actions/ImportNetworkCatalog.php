<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ingestion\CatalogLine;
use App\Ingestion\CatalogSnapshot;
use App\Ingestion\CatalogStation;
use App\Ingestion\CatalogStop;
use App\Ingestion\NetworkCatalogs;
use App\Models\CatalogImport;
use App\Models\Line;
use App\Models\Station;
use App\Models\Stop;
use App\Support\FeedRecordings;
use App\Support\NetworkShapes;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class ImportNetworkCatalog
{
    private const int CHUNK = 1000;

    public function __construct(
        private NetworkCatalogs $catalogs,
        private NetworkShapes $shapes,
        private FeedRecordings $recordings,
    ) {}

    /**
     * Replace a network's lines and stops with its current catalogue, then its shapes file when the shapes changed.
     *
     * @return array{lines: int, stops: int}|null null when the catalogue has not changed
     */
    public function handle(string $network): ?array
    {
        $previous = CatalogImport::query()->find($network, ['network', 'etag', 'shapes_fingerprint']);
        // Without shapes (first import since E3.8 or since they moved to the database, a failed shapes step, shapes
        // lost), the archive is downloaded again, even if unchanged.
        // Recordings without their catalogue (the first import since they were turned on), likewise (E5.1), and a
        // network without stations (the first import since E7.1).
        $unchangedIfSame = $previous?->shapes_fingerprint !== null && $this->shapes->has($network)
            && Station::query()->where('network', $network)->exists()
            && (! $this->recordings->enabled($network) || $this->recordings->hasCatalog($network));
        $snapshot = $this->catalogs->for($network)->fetch($unchangedIfSame ? $previous->etag : null);

        if ($snapshot === null) {
            return null;
        }

        $counts = $this->replace($network, $snapshot, $previous?->shapes_fingerprint);

        if ($this->recordings->enabled($network)) {
            // The catalogue is imported: a recording that cannot keep it still has the previous one.
            try {
                $this->recordings->keepCatalog($network, $snapshot);
            } catch (Throwable $exception) {
                Log::warning("Catalogue of {$network} could not be kept for the recordings.", ['exception' => $exception]);
            }
        }

        return $counts;
    }

    /**
     * Replace a network's lines, stops and shapes with a catalogue already at hand: a replayed day's (E5.2). Its ETag
     * is not the live one, so the next import downloads the live catalogue in full.
     *
     * @return array{lines: int, stops: int}
     */
    public function replaceWith(string $network, CatalogSnapshot $snapshot): array
    {
        return $this->replace($network, $snapshot, CatalogImport::query()->find($network)?->shapes_fingerprint);
    }

    /**
     * @return array{lines: int, stops: int}
     */
    private function replace(string $network, CatalogSnapshot $snapshot, ?string $previousShapesFingerprint): array
    {
        // Rows not touched by this run belong to lines or stops that left the feed.
        $syncId = (string) Str::uuid();

        $counts = DB::transaction(function () use ($network, $snapshot, $syncId): array {
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

            // A feed without lines is broken, not empty: keep the current catalogue.
            if ($lines === 0) {
                throw new RuntimeException("The {$network} catalogue has no lines; the current one is kept.");
            }

            $stops = $this->upsert(Stop::class, $snapshot->stops(), fn (CatalogStop $stop): array => [
                'id' => $stop->id,
                'network' => $network,
                'name' => $stop->name,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
                'station_id' => $stop->stationId,
                'sync_id' => $syncId,
            ]);

            $this->upsert(Station::class, $snapshot->stations(), fn (CatalogStation $station): array => [
                'id' => $station->id,
                'network' => $network,
                'name' => $station->name,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'sync_id' => $syncId,
            ]);

            Line::query()->where('network', $network)->where('sync_id', '!=', $syncId)->delete();
            Stop::query()->where('network', $network)->where('sync_id', '!=', $syncId)->delete();
            Station::query()->where('network', $network)->where('sync_id', '!=', $syncId)->delete();

            CatalogImport::query()->updateOrCreate(
                ['network' => $network],
                ['imported_at' => now()],
            );

            return ['lines' => $lines, 'stops' => $stops];
        });

        // After the lines are committed: a failed import never leaves a file naming lines the catalogue lacks.
        $fingerprint = $this->shapes->store(
            $network,
            $snapshot->shapes(),
            Line::query()->where('network', $network)->get()->map(fn (Line $line): string => $line->id)->all(),
            $previousShapesFingerprint,
        );
        // The ETag is saved last: when the shapes step fails, the next run downloads the archive again.
        CatalogImport::query()->whereKey($network)->update(['etag' => $snapshot->etag(), 'shapes_fingerprint' => $fingerprint]);

        return $counts;
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
