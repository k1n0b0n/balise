<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Recorded service days replayed locally (E5.2, ADR-0022): downloaded from the site's `/recordings/`, unpacked on the
 * `replays` disk (`{network}-{service day}/`), and the replay in progress in `{network}.json`.
 */
final readonly class Replays
{
    public function __construct(private Http $http) {}

    /**
     * @return array{day: string, from: string, started_at: int}|null
     */
    public function current(string $network): ?array
    {
        if (! $this->disk()->exists("{$network}.json")) {
            return null;
        }

        /** @var array{day: string, from: string, started_at: int} */
        return json_decode((string) $this->disk()->get("{$network}.json"), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Replays that day from that time of day (HH:MM, the network's time zone), starting now.
     */
    public function start(string $network, string $day, string $from, CarbonImmutable $now): void
    {
        $this->disk()->put("{$network}.json", (string) json_encode(['day' => $day, 'from' => $from, 'started_at' => $now->getTimestamp()]));
    }

    public function stop(string $network): void
    {
        $this->disk()->delete("{$network}.json");
    }

    public function directory(string $network, string $day): string
    {
        return $this->disk()->path("{$network}-{$day}");
    }

    public function has(string $network, string $day): bool
    {
        return $this->disk()->exists("{$network}-{$day}/manifest.json");
    }

    /**
     * The recorded time a replay shows now, in seconds since the epoch: from the time asked for, at the recorded pace,
     * in a loop from the day's first payload to its last. Null when the network is not replaying.
     */
    public function replayedAt(string $network, CarbonImmutable $now): ?int
    {
        $replay = $this->current($network);

        if ($replay === null) {
            return null;
        }

        /** @var array{first: string, last: string} $manifest */
        $manifest = json_decode((string) $this->disk()->get("{$network}-{$replay['day']}/manifest.json"), associative: true, flags: JSON_THROW_ON_ERROR);
        $first = CarbonImmutable::parse($manifest['first'])->getTimestamp();
        // One feed interval after the last payload, so that it is shown as long as the others before the loop.
        $length = CarbonImmutable::parse($manifest['last'])->getTimestamp() - $first + Config::integer("networks.{$network}.vehicles.poll_every_seconds");
        $from = CarbonImmutable::parse("{$replay['day']} {$replay['from']}", Config::string("networks.{$network}.timezone"))->getTimestamp();
        $offset = min(max($from - $first, 0), $length - 1) + $now->getTimestamp() - $replay['started_at'];

        return $first + $offset % $length;
    }

    /**
     * Downloads and unpacks a recorded day, the latest one when none is given.
     *
     * @return string the service day
     */
    public function download(string $network, ?string $day): string
    {
        $source = $this->source();
        /** @var array{archives: list<array{file: string, network: string, service_day: string}>} $index */
        $index = $this->http->timeout(30)->get("{$source}index.json")->throw()->json();
        $archives = array_values(array_filter($index['archives'], fn (array $archive): bool => $archive['network'] === $network && ($day === null || $archive['service_day'] === $day)));

        if ($archives === []) {
            throw new RuntimeException($day === null ? "No recording of {$network} at {$source}." : "No recording of {$network} on {$day} at {$source}: see {$source}index.json.");
        }

        // The index lists the newest first.
        $archive = $archives[0];
        $directory = $this->directory($network, $archive['service_day']);
        $tar = "{$directory}.tar";
        File::ensureDirectoryExists($directory);

        try {
            $this->http->timeout(600)->sink($tar)->get($source.$archive['file'])->throw();
            // A day is about 90 MB, beyond the default 60 s on a slow disk.
            Process::timeout(300)->run(['tar', '-xf', $tar, '-C', $directory])->throw();
        } finally {
            File::delete($tar);
        }

        return $archive['service_day'];
    }

    /**
     * Where the archives are: `RECORDINGS_SOURCE_URL`, or the site named in the repository's *Website* field on
     * GitHub, which keeps the production address out of the repository.
     */
    private function source(): string
    {
        $source = Config::get('recordings.source_url');

        if (! is_string($source) || $source === '') {
            $repository = Config::string('recordings.repository');
            $homepage = $this->http->timeout(10)->get("https://api.github.com/repos/{$repository}")->throw()->json('homepage');

            if (! is_string($homepage) || $homepage === '') {
                throw new RuntimeException("No source of recordings: set RECORDINGS_SOURCE_URL, or the Website field of {$repository} on GitHub.");
            }

            $source = rtrim($homepage, '/').'/recordings/';
        }

        return rtrim($source, '/').'/';
    }

    private function disk(): Filesystem
    {
        return Storage::disk('replays');
    }
}
