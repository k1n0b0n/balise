<?php

declare(strict_types=1);

namespace App\Ingestion\GtfsRealtime;

use App\Ingestion\VehicleFeed;
use App\Ingestion\VehicleFeedSnapshot;
use App\Ingestion\VehiclePosition;
use Carbon\CarbonImmutable;
use Closure;
use DateInterval;
use RuntimeException;

/**
 * Replays a recorded service day (E5.2, ADR-0022): the payload recorded at the replayed time, decoded as the live feed
 * is, with its times moved to now, so that freshness, the poll's wait and `Next-Version-At` work unchanged.
 */
final readonly class ReplayFeed implements VehicleFeed
{
    /**
     * @param  string  $directory  an unpacked archive, with its payloads in `vehicles/`
     * @param  Closure(): int  $replayedAt  the recorded time being replayed, in seconds since the epoch
     */
    public function __construct(
        private string $directory,
        private Closure $replayedAt,
        private GtfsRealtimeDecoder $decoder,
    ) {}

    public function fetch(?string $etag): ?VehicleFeedSnapshot
    {
        $replayedAt = ($this->replayedAt)();
        $file = $this->payloadAt($replayedAt);
        // The payload's name: the poll's If-None-Match finds an unchanged payload as it finds an unchanged feed.
        $payloadEtag = '"'.basename($file, '.pb.gz').'"';

        if ($etag === $payloadEtag) {
            return null;
        }

        $snapshot = $this->decoder->decode((string) gzdecode((string) file_get_contents($file)), $payloadEtag);
        $shift = new DateInterval('PT'.max(0, CarbonImmutable::now()->getTimestamp() - $replayedAt).'S');

        return new VehicleFeedSnapshot(
            $payloadEtag,
            array_map(fn (VehiclePosition $position): VehiclePosition => new VehiclePosition(
                vehicleId: $position->vehicleId,
                latitude: $position->latitude,
                longitude: $position->longitude,
                recordedAt: $position->recordedAt->add($shift),
                lineId: $position->lineId,
                tripId: $position->tripId,
                destination: $position->destination,
            ), $snapshot->positions),
            $snapshot->publishedAt?->add($shift),
        );
    }

    /**
     * The last payload recorded at or before that time; the first one before the day's first payload.
     */
    private function payloadAt(int $time): string
    {
        $files = glob("{$this->directory}/vehicles/*.pb.gz") ?: [];

        if ($files === []) {
            throw new RuntimeException("No recorded payload in {$this->directory}.");
        }

        sort($files);
        $chosen = $files[0];
        $stamp = CarbonImmutable::createFromTimestamp($time, 'UTC')->format('Ymd\THis\Z');

        foreach ($files as $file) {
            if (basename($file, '.pb.gz') > $stamp) {
                break;
            }

            $chosen = $file;
        }

        return $chosen;
    }
}
