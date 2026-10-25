<?php

declare(strict_types=1);

namespace App\Ingestion\GtfsRealtime;

use App\Ingestion\VehicleFeedSnapshot;
use App\Ingestion\VehiclePosition;
use DateTimeImmutable;
use Transit_realtime\FeedEntity;
use Transit_realtime\FeedMessage;
use UnexpectedValueException;

/**
 * Reads a GTFS-Realtime payload into normalised positions, for the live feed and for its replay (E5.2).
 */
final readonly class GtfsRealtimeDecoder
{
    /**
     * Where the vehicle id comes from, in order of preference (ADR-0013): some feeds omit `vehicle.id`.
     */
    public const array VEHICLE_ID_SOURCES = ['vehicle.id', 'vehicle.label', 'entity.id'];

    /**
     * @param  list<string>  $vehicleIdSources
     */
    public function __construct(
        private string $network,
        private array $vehicleIdSources,
        private bool $labelIsDestination,
    ) {}

    public function decode(string $payload, ?string $etag): VehicleFeedSnapshot
    {
        $feed = new FeedMessage;
        $feed->mergeFromString($payload);

        // An empty or non-protobuf body can decode to an empty message: refuse it rather than report no vehicles.
        if (! $feed->hasHeader()) {
            throw new UnexpectedValueException('The GTFS-RT payload has no header.');
        }

        $positions = [];
        $headerTimestamp = (int) $feed->getHeader()?->getTimestamp();

        foreach ($feed->getEntity() as $entity) {
            $position = $this->position($entity, $headerTimestamp);

            if ($position !== null) {
                $positions[] = $position;
            }
        }

        return new VehicleFeedSnapshot(
            $etag,
            $positions,
            $headerTimestamp > 0 ? new DateTimeImmutable("@{$headerTimestamp}") : null,
            $payload,
        );
    }

    private function position(FeedEntity $entity, int $headerTimestamp): ?VehiclePosition
    {
        $vehicle = $entity->getVehicle();
        $coordinates = $vehicle?->getPosition();

        if ($vehicle === null || $coordinates === null) {
            return null;
        }

        $latitude = $coordinates->getLatitude();
        $longitude = $coordinates->getLongitude();

        // (0, 0) is what a position without its coordinates decodes to, often sent when the GPS is lost.
        if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180 || ($latitude === 0.0 && $longitude === 0.0)) {
            return null;
        }

        $vehicleId = $this->vehicleId($entity);
        $timestamp = (int) $vehicle->getTimestamp() ?: $headerTimestamp;

        // Without an id or a time, a position cannot be stored nor aged.
        if ($vehicleId === null || $timestamp === 0) {
            return null;
        }

        $trip = $vehicle->getTrip();
        $label = $vehicle->getVehicle()?->getLabel();

        return new VehiclePosition(
            vehicleId: $this->id($vehicleId),
            latitude: $latitude,
            longitude: $longitude,
            recordedAt: new DateTimeImmutable("@{$timestamp}"),
            lineId: $this->optionalId($trip?->getRouteId()),
            tripId: $this->optionalId($trip?->getTripId()),
            destination: $this->labelIsDestination && $label !== null && $label !== '' ? $label : null,
        );
    }

    private function vehicleId(FeedEntity $entity): ?string
    {
        $descriptor = $entity->getVehicle()?->getVehicle();

        foreach ($this->vehicleIdSources as $source) {
            $value = match ($source) {
                'vehicle.id' => $descriptor?->getId(),
                'vehicle.label' => $descriptor?->getLabel(),
                'entity.id' => $entity->getId(),
                default => null,
            };

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function optionalId(?string $sourceId): ?string
    {
        return $sourceId === null || $sourceId === '' ? null : $this->id($sourceId);
    }

    private function id(string $sourceId): string
    {
        return "{$this->network}:{$sourceId}";
    }
}
