<?php

declare(strict_types=1);

namespace App\Support;

use App\Ingestion\CatalogSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The feeds recorded on the server, one archive per service day (E5.1, ADR-0022).
 *
 * Raw files, on the `recordings` disk: `{network}/days/{service day}/vehicles/{feed time}.pb.gz`, the last catalogue in
 * `{network}/catalog.zip`, and the day being recorded in `{network}/state.json`. Archives, on the `recording-archives`
 * disk: `{network}-{service day}.tar`, its manifest beside it, and `index.json`.
 */
final readonly class FeedRecordings
{
    public function enabled(string $network): bool
    {
        // Off for a network without a `recordings` block: adding a network stays a matter of configuration (E6.1).
        return Config::boolean("networks.{$network}.recordings.enabled", false);
    }

    /**
     * Keeps a vehicle payload in the service day it belongs to. A feed without a fresh vehicle is not kept: the
     * network is empty, or between two service days.
     */
    public function record(string $network, string $payload, CarbonImmutable $feedTime, int $freshVehicles, CarbonImmutable $now): void
    {
        if ($freshVehicles === 0) {
            return;
        }

        $state = $this->state($network);
        $local = $now->setTimezone(Config::string("networks.{$network}.timezone"));
        // A new day after a gap, named after the date it begins (the same day again when the gap was within one
        // date: a feed down at noon), or at the cut of a network that never empties.
        $day = $local->toDateString();

        if ($state !== null
            && $now->getTimestamp() - $state['last_vehicle_at'] < Config::integer('recordings.service_gap_minutes') * 60
            && $local->lessThan($this->cut($network, $state['day']))) {
            $day = $state['day'];
        }

        $stamp = $feedTime->setTimezone('UTC')->format('Ymd\THis\Z');
        $this->raw()->put("{$network}/days/{$day}/vehicles/{$stamp}.pb.gz", (string) gzencode($payload));
        $this->raw()->put("{$network}/state.json", (string) json_encode(['day' => $day, 'last_vehicle_at' => $now->getTimestamp()]));
    }

    /**
     * Keeps the catalogue just downloaded, the one the next archives carry.
     */
    public function keepCatalog(string $network, CatalogSnapshot $catalog): void
    {
        File::ensureDirectoryExists($this->raw()->path($network));
        $catalog->copyTo($this->raw()->path("{$network}/catalog.zip"));
    }

    public function hasCatalog(string $network): bool
    {
        return $this->raw()->exists("{$network}/catalog.zip");
    }

    /**
     * Archives every finished day not archived yet, then keeps the last archives only and lists them.
     *
     * A day is finished once a later date has begun in the network's time zone and the network has been empty for the
     * service gap, or another day is being recorded. A day dated today is left for the next night: the network may
     * still be about to start it.
     *
     * @return list<string> the service days archived
     */
    public function archive(string $network, CarbonImmutable $now): array
    {
        $state = $this->state($network);
        $today = $now->setTimezone(Config::string("networks.{$network}.timezone"))->toDateString();
        $running = $state !== null && $now->getTimestamp() - $state['last_vehicle_at'] < Config::integer('recordings.service_gap_minutes') * 60;
        $archived = [];

        $directories = $this->raw()->directories("{$network}/days");
        sort($directories);

        foreach ($directories as $directory) {
            $day = basename($directory);

            if ($day >= $today || ($running && $day === $state['day'])) {
                continue;
            }

            $this->archiveDay($network, $day, $directory);
            $archived[] = $day;
        }

        $this->keepLast($network);
        $this->writeIndex();

        return $archived;
    }

    private function archiveDay(string $network, string $day, string $directory): void
    {
        $payloads = $this->raw()->files("{$directory}/vehicles");
        sort($payloads);
        $catalog = $this->hasCatalog($network);
        $manifest = [
            'network' => $network,
            'service_day' => $day,
            'first' => $this->time($payloads[0] ?? null),
            'last' => $this->time($payloads === [] ? null : $payloads[array_key_last($payloads)]),
            'payloads' => count($payloads),
            'catalog' => $catalog,
            'attribution' => Config::string("networks.{$network}.attribution"),
        ];

        // The day's folder becomes the archive's content, so that tar takes it as it is.
        $this->raw()->put("{$directory}/manifest.json", $this->json($manifest));
        $this->raw()->put("{$directory}/README.md", $this->readme($network, $day));
        if ($catalog) {
            $this->raw()->copy("{$network}/catalog.zip", "{$directory}/catalog.zip");
        }

        $name = "{$network}-{$day}";
        // Written under a hidden name, which nginx does not serve, then renamed: a download never gets half an archive.
        File::ensureDirectoryExists($this->archives()->path(''));
        $partial = $this->archives()->path(".{$name}.tar");
        Process::run(['tar', '-cf', $partial, '-C', $this->raw()->path($directory), 'README.md', 'manifest.json', 'vehicles', ...($catalog ? ['catalog.zip'] : [])])->throw();
        File::move($partial, $this->archives()->path("{$name}.tar"));

        // Beside the archive, for the list: reading it back from the archive would mean opening each one.
        $this->archives()->put("{$name}.json", $this->json(['file' => "{$name}.tar", 'bytes' => $this->archives()->size("{$name}.tar"), ...$manifest]));
        $this->raw()->deleteDirectory($directory);
    }

    private function keepLast(string $network): void
    {
        $archives = array_values(array_filter(
            $this->archives()->files(),
            fn (string $file): bool => str_starts_with($file, "{$network}-") && str_ends_with($file, '.tar'),
        ));
        // Named by date: the order of names is the order of days.
        rsort($archives);

        foreach (array_slice($archives, Config::integer('recordings.keep')) as $archive) {
            $this->archives()->delete([$archive, basename($archive, '.tar').'.json']);
        }
    }

    /**
     * Lists every archive of every network, newest first within a network, for whoever downloads them.
     */
    private function writeIndex(): void
    {
        $manifests = array_values(array_filter(
            $this->archives()->files(),
            fn (string $file): bool => str_ends_with($file, '.json') && $file !== 'index.json',
        ));
        rsort($manifests);

        $archives = array_map(fn (string $file): mixed => json_decode((string) $this->archives()->get($file), associative: true), $manifests);
        $this->archives()->put('index.json', $this->json(['archives' => $archives]));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function json(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function readme(string $network, string $day): string
    {
        $name = Config::string("networks.{$network}.name");
        $attribution = Config::string("networks.{$network}.attribution");

        return <<<MD
            # {$name}, service day of {$day}

            Live vehicle positions recorded by balise, from the network's first vehicle to its last (ADR-0022).

            - `vehicles/`: every GTFS-Realtime `FeedMessage` read, as received and gzipped, named by the feed's time in UTC.
            - `catalog.zip`: the static GTFS the positions refer to, when one was recorded.
            - `manifest.json`: the first and last times, and the number of payloads.

            Source: {$attribution}.

            MD;
    }

    /**
     * The feed time of a payload's file name, in ISO 8601.
     */
    private function time(?string $file): ?string
    {
        return $file === null ? null : CarbonImmutable::createFromFormat('Ymd\THis\Z', basename($file, '.pb.gz'), 'UTC')?->toIso8601ZuluString();
    }

    /**
     * When the day begun at that date is cut, if the network has not emptied before.
     */
    private function cut(string $network, string $day): CarbonImmutable
    {
        return CarbonImmutable::parse($day.' '.Config::string("networks.{$network}.recordings.service_day_cut_at"), Config::string("networks.{$network}.timezone"))->addDay();
    }

    /**
     * @return array{day: string, last_vehicle_at: int}|null
     */
    private function state(string $network): ?array
    {
        if (! $this->raw()->exists("{$network}/state.json")) {
            return null;
        }

        /** @var array{day: string, last_vehicle_at: int} */
        return json_decode((string) $this->raw()->get("{$network}/state.json"), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function raw(): Filesystem
    {
        return Storage::disk('recordings');
    }

    private function archives(): Filesystem
    {
        return Storage::disk('recording-archives');
    }
}
