<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\PositionsUpdated;
use App\Ingestion\VehiclePosition;
use App\Models\VehiclePosition as StoredPosition;
use Carbon\CarbonImmutable;
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
            $stored = StoredPosition::query()->where('network', $network)->get()->keyBy('vehicle_id');

            $rows = [];

            foreach ($fresh as $vehicleId => $position) {
                $current = $stored->get($vehicleId);

                if ($current instanceof StoredPosition && ! $this->differs($current, $position)) {
                    continue;
                }

                $rows[] = [
                    'vehicle_id' => $vehicleId,
                    'network' => $network,
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'line_id' => $position->lineId,
                    'trip_id' => $position->tripId,
                    'recorded_at' => CarbonImmutable::instance($position->recordedAt),
                    'received_at' => $receivedAt,
                ];
            }

            if ($rows !== []) {
                StoredPosition::query()->upsert($rows, ['vehicle_id'], array_keys($rows[0]));
            }

            $removed = $stored
                ->filter(fn (StoredPosition $current): bool => ! isset($fresh[$current->vehicle_id]) && $current->recorded_at->lt($oldest))
                ->keys()
                ->map(fn (int|string $vehicleId): string => (string) $vehicleId)
                ->values()
                ->all();

            if ($removed !== []) {
                StoredPosition::query()->whereKey($removed)->delete();
            }

            $changed = StoredPosition::query()->whereKey(array_column($rows, 'vehicle_id'))->get()->values()->all();

            return [$changed, $removed];
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

    private function differs(StoredPosition $stored, VehiclePosition $position): bool
    {
        return $stored->latitude !== $position->latitude
            || $stored->longitude !== $position->longitude
            || $stored->line_id !== $position->lineId
            || $stored->trip_id !== $position->tripId
            || $stored->recorded_at->getTimestamp() !== $position->recordedAt->getTimestamp();
    }
}
