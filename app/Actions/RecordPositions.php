<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\PositionsUpdated;
use App\Ingestion\VehiclePosition;
use App\Models\VehiclePosition as StoredPosition;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for vehicle positions (ADR-0002, ADR-0013).
 */
final readonly class RecordPositions
{
    /**
     * Keep the latest fresh position per vehicle, drop the stale ones, and announce what changed.
     *
     * @param  list<VehiclePosition>  $positions  an empty list only ages the current state out
     * @return array{dropped: int, changed: int, removed: int}
     */
    public function handle(string $network, array $positions, CarbonImmutable $receivedAt): array
    {
        $oldest = $receivedAt->subSeconds(Config::integer("networks.{$network}.vehicles.max_age_seconds"));
        $newest = $receivedAt->addSeconds(Config::integer("networks.{$network}.vehicles.max_ahead_seconds"));

        $fresh = [];
        $dropped = 0;

        foreach ($positions as $position) {
            $recordedAt = CarbonImmutable::instance($position->recordedAt);

            if ($recordedAt->lt($oldest) || $recordedAt->gt($newest)) {
                $dropped++;

                continue;
            }

            // A feed may list a vehicle twice: keep its latest position.
            $previous = $fresh[$position->vehicleId] ?? null;

            if ($previous === null || $previous->recordedAt < $position->recordedAt) {
                $fresh[$position->vehicleId] = $position;
            }
        }

        [$changed, $removed] = DB::transaction(function () use ($network, $fresh, $oldest, $receivedAt): array {
            // Plain rows of the compared columns, without a model per stored vehicle (story 021).
            /** @var Collection<string, object{vehicle_id: string, latitude: float, longitude: float, line_id: ?string, trip_id: ?string, destination: ?string, recorded_at: string}> $stored */
            $stored = StoredPosition::query()->toBase()
                ->where('network', $network)
                ->get(['vehicle_id', 'latitude', 'longitude', 'line_id', 'trip_id', 'destination', 'recorded_at'])
                ->keyBy('vehicle_id');

            $rows = [];

            foreach ($fresh as $vehicleId => $position) {
                $current = $stored->get($vehicleId);

                // Unchanged, or a late report older than what is stored: keep the stored position.
                if ($current !== null
                    && (! $this->differs($current, $position) || $this->timestamp($current->recorded_at) > $position->recordedAt->getTimestamp())) {
                    continue;
                }

                $rows[] = [
                    'vehicle_id' => $vehicleId,
                    'network' => $network,
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'line_id' => $position->lineId,
                    'trip_id' => $position->tripId,
                    'destination' => $position->destination,
                    'recorded_at' => CarbonImmutable::instance($position->recordedAt),
                    'received_at' => $receivedAt,
                ];
            }

            if ($rows !== []) {
                StoredPosition::query()->upsert($rows, ['vehicle_id'], array_keys($rows[0]));
            }

            $removed = $stored
                ->filter(fn (object $current): bool => ! isset($fresh[$current->vehicle_id]) && $this->timestamp($current->recorded_at) < $oldest->getTimestamp())
                ->keys()
                ->map(fn (int|string $vehicleId): string => (string) $vehicleId)
                ->values()
                ->all();

            if ($removed !== []) {
                StoredPosition::query()->whereKey($removed)->delete();
            }

            // The event carries the rows just written, as models, without reading them back.
            return [StoredPosition::hydrate($rows)->all(), $removed];
        });

        if ($changed !== [] || $removed !== []) {
            PositionsUpdated::dispatch($network, array_values($changed), array_values($removed));
        }

        return [
            'dropped' => $dropped,
            'changed' => count($changed),
            'removed' => count($removed),
        ];
    }

    /**
     * @param  object{latitude: float, longitude: float, line_id: ?string, trip_id: ?string, destination: ?string, recorded_at: string}  $stored
     */
    private function differs(object $stored, VehiclePosition $position): bool
    {
        return $stored->latitude !== $position->latitude
            || $stored->longitude !== $position->longitude
            || $stored->line_id !== $position->lineId
            || $stored->trip_id !== $position->tripId
            || $stored->destination !== $position->destination
            || $this->timestamp($stored->recorded_at) !== $position->recordedAt->getTimestamp();
    }

    /**
     * Seconds since the epoch of a timestamp as PostgreSQL returns it ("2026-10-16 12:00:00+00").
     */
    private function timestamp(string $value): int
    {
        return new DateTimeImmutable($value)->getTimestamp();
    }
}
