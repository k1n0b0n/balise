<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\PositionsUpdated;
use App\Models\VehiclePosition;
use Illuminate\Contracts\Cache\Repository;

/**
 * Recent changes of each network's vehicles, so that browsers download only what changed since their last request (ADR-0006).
 *
 * Every PositionsUpdated event becomes a numbered version; only the last versions are kept, and a browser that
 * fell further behind gets the whole state again.
 */
final readonly class PositionChangeLog
{
    /** About five minutes of changes at one poll every 20 s. */
    public const int KEPT_VERSIONS = 15;

    public function __construct(private Repository $cache) {}

    public function record(PositionsUpdated $event): void
    {
        $versions = $this->versions($event->network);

        $versions[] = [
            // Counted by the database, so it never comes back after the cache is cleared, and has no gap (ADR-0021).
            'version' => $event->version,
            'changed' => array_map(fn (VehiclePosition $position): string => $position->vehicle_id, $event->positions),
            'removed' => $event->removedVehicleIds,
        ];

        $this->cache->forever($this->key($event->network), array_slice($versions, -self::KEPT_VERSIONS));
    }

    /**
     * The current version of the network's vehicles, 0 before the first change.
     */
    public function version(string $network): int
    {
        $versions = $this->versions($network);

        return $versions === [] ? 0 : $versions[array_key_last($versions)]['version'];
    }

    /**
     * Vehicles changed and removed after the given version, or null when the log cannot say: that version is unknown
     * or no longer kept, or a version after it is missing, which a write to the cache that failed, or a cache that
     * restarted, leaves behind (ADR-0021). A newest version missing is seen only when the next one is written.
     *
     * @return array{changed: list<string>, removed: list<string>}|null
     */
    public function since(string $network, int $version): ?array
    {
        $versions = $this->versions($network);
        $expected = $version + 1;

        foreach ($versions as $entry) {
            if ($entry['version'] <= $version) {
                continue;
            }

            if ($entry['version'] !== $expected++) {
                return null;
            }
        }

        // No version after it: it must be the current one, not one the log never had.
        if ($expected === $version + 1 && $version !== $this->version($network)) {
            return null;
        }

        $changed = [];
        $removed = [];

        foreach ($versions as $entry) {
            if ($entry['version'] <= $version) {
                continue;
            }

            // Applied in order: a vehicle removed then seen again counts as changed, and the reverse as removed.
            foreach ($entry['changed'] as $vehicleId) {
                $changed[$vehicleId] = true;
                unset($removed[$vehicleId]);
            }

            foreach ($entry['removed'] as $vehicleId) {
                $removed[$vehicleId] = true;
                unset($changed[$vehicleId]);
            }
        }

        return ['changed' => array_keys($changed), 'removed' => array_keys($removed)];
    }

    /**
     * @return list<array{version: int, changed: list<string>, removed: list<string>}>
     */
    private function versions(string $network): array
    {
        /** @var list<array{version: int, changed: list<string>, removed: list<string>}> */
        return $this->cache->get($this->key($network), []);
    }

    private function key(string $network): string
    {
        return "position-changes:{$network}";
    }
}
