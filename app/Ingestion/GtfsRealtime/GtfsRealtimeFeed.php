<?php

declare(strict_types=1);

namespace App\Ingestion\GtfsRealtime;

use App\Ingestion\VehicleFeed;
use App\Ingestion\VehicleFeedSnapshot;
use App\Ingestion\VehiclePosition;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as Http;
use Transit_realtime\FeedEntity;
use Transit_realtime\FeedMessage;

final readonly class GtfsRealtimeFeed implements VehicleFeed
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
        private string $url,
        private array $vehicleIdSources,
        private Http $http,
    ) {}

    public function fetch(?string $etag): ?VehicleFeedSnapshot
    {
        $response = $this->http
            ->connectTimeout(5)
            ->timeout(10)
            ->withHeaders($etag === null ? [] : ['If-None-Match' => $etag])
            ->get($this->url)
            ->throw();

        if ($response->status() === 304) {
            return null;
        }

        $feed = new FeedMessage;
        $feed->mergeFromString($response->body());

        $positions = [];

        foreach ($feed->getEntity() as $entity) {
            $position = $this->position($entity, (int) $feed->getHeader()?->getTimestamp());

            if ($position !== null) {
                $positions[] = $position;
            }
        }

        return new VehicleFeedSnapshot($response->header('ETag') ?: null, $positions);
    }

    private function position(FeedEntity $entity, int $headerTimestamp): ?VehiclePosition
    {
        $vehicle = $entity->getVehicle();
        $coordinates = $vehicle?->getPosition();

        if ($vehicle === null || $coordinates === null) {
            return null;
        }

        $vehicleId = $this->vehicleId($entity);
        $timestamp = (int) $vehicle->getTimestamp() ?: $headerTimestamp;

        // Without an id or a time, a position cannot be stored nor aged.
        if ($vehicleId === null || $timestamp === 0) {
            return null;
        }

        $trip = $vehicle->getTrip();

        return new VehiclePosition(
            vehicleId: $this->id($vehicleId),
            latitude: $coordinates->getLatitude(),
            longitude: $coordinates->getLongitude(),
            recordedAt: new DateTimeImmutable("@{$timestamp}"),
            lineId: $this->optionalId($trip?->getRouteId()),
            tripId: $this->optionalId($trip?->getTripId()),
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
