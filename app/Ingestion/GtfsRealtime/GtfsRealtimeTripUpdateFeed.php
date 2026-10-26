<?php

declare(strict_types=1);

namespace App\Ingestion\GtfsRealtime;

use App\Ingestion\StopTime;
use App\Ingestion\TripStatus;
use App\Ingestion\TripUpdate;
use App\Ingestion\TripUpdateFeed;
use App\Ingestion\TripUpdateFeedSnapshot;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;
use Transit_realtime\FeedMessage;
use Transit_realtime\TripDescriptor\ScheduleRelationship as TripRelationship;
use Transit_realtime\TripUpdate as GtfsTripUpdate;
use Transit_realtime\TripUpdate\StopTimeUpdate;
use Transit_realtime\TripUpdate\StopTimeUpdate\ScheduleRelationship as StopRelationship;
use UnexpectedValueException;

/**
 * Reads a GTFS-Realtime trip updates feed (E8.1). Its payloads reach megabytes: the C extension decodes them in
 * milliseconds, the pure-PHP runtime in more than a second and hundreds of megabytes (ADR-0023).
 */
final readonly class GtfsRealtimeTripUpdateFeed implements TripUpdateFeed
{
    public function __construct(
        private string $network,
        private string $url,
        private Http $http,
    ) {}

    public function fetch(): TripUpdateFeedSnapshot
    {
        if (! extension_loaded('protobuf')) {
            throw new RuntimeException('Reading trip updates needs the protobuf extension (ADR-0023).');
        }

        // The producer ignores If-None-Match on this feed (measured 2026-10-25): every read is a full answer.
        $payload = $this->http->connectTimeout(5)->timeout(15)->get($this->url)->throw()->body();

        return $this->decode($payload);
    }

    public function decode(string $payload): TripUpdateFeedSnapshot
    {
        $feed = new FeedMessage;
        $feed->mergeFromString($payload);

        if (! $feed->hasHeader()) {
            throw new UnexpectedValueException('The GTFS-RT payload has no header.');
        }

        $trips = [];

        foreach ($feed->getEntity() as $entity) {
            $update = $entity->getTripUpdate();
            $tripId = $update?->getTrip()?->getTripId();

            if ($update !== null && $tripId !== null && $tripId !== '') {
                $trips[] = $this->trip($update, $tripId);
            }
        }

        $timestamp = (int) $feed->getHeader()?->getTimestamp();

        return new TripUpdateFeedSnapshot($trips, $timestamp > 0 ? new DateTimeImmutable("@{$timestamp}") : null);
    }

    private function trip(GtfsTripUpdate $update, string $tripId): TripUpdate
    {
        $stopTimes = [];

        foreach ($update->getStopTimeUpdate() as $stopTime) {
            if ($stopTime->getStopId() !== '') {
                $stopTimes[] = $this->stopTime($stopTime);
            }
        }

        return new TripUpdate(
            tripId: $this->id($tripId),
            status: match ($update->getTrip()?->getScheduleRelationship()) {
                TripRelationship::CANCELED, TripRelationship::DELETED => TripStatus::Cancelled,
                TripRelationship::ADDED, TripRelationship::PBNEW, TripRelationship::UNSCHEDULED, TripRelationship::DUPLICATED => TripStatus::Added,
                default => TripStatus::Scheduled,
            },
            stopTimes: $stopTimes,
            lineId: $this->optionalId($update->getTrip()?->getRouteId()),
            vehicleId: $this->optionalId($update->getVehicle()?->getId()),
        );
    }

    private function stopTime(StopTimeUpdate $stopTime): StopTime
    {
        // The departure, or the arrival at a trip's last stop; `time` is absolute and already includes the delay.
        $time = (int) ($stopTime->getDeparture()?->getTime() ?: $stopTime->getArrival()?->getTime());

        return new StopTime(
            stopId: $this->id($stopTime->getStopId()),
            time: $time > 0 ? new DateTimeImmutable("@{$time}") : null,
            skipped: $stopTime->getScheduleRelationship() === StopRelationship::SKIPPED,
        );
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
