<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RecordPositions;
use App\Ingestion\VehicleFeeds;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('network:poll {network : Network key from config/networks.php}')]
#[Description('Fetch the live vehicle positions of a network')]
final class PollNetwork extends Command
{
    public function handle(VehicleFeeds $feeds, RecordPositions $record): int
    {
        $network = $this->argument('network');

        if (! array_key_exists($network, Config::array('networks'))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', array_keys(Config::array('networks'))).'.');

            return self::INVALID;
        }

        $etagKey = "vehicle-feed:{$network}:etag";
        $receivedAt = CarbonImmutable::now();
        $etag = Cache::get($etagKey);

        try {
            $snapshot = $feeds->for($network)->fetch(is_string($etag) ? $etag : null);
        } catch (Throwable $exception) {
            // The current state stays as it is; the next scheduled run retries.
            Log::warning("Vehicle feed of {$network} could not be read.", ['exception' => $exception]);
            $this->components->error("{$network}: feed unavailable ({$exception->getMessage()}).");

            return self::FAILURE;
        }

        if ($snapshot === null) {
            // Nothing new, but vehicles that stopped reporting still age out.
            $counts = $record->handle($network, [], $receivedAt);
            $this->components->info("{$network}: feed unchanged, {$counts['removed']} removed.");

            return self::SUCCESS;
        }

        $counts = $record->handle($network, $snapshot->positions, $receivedAt);
        Cache::put($etagKey, $snapshot->etag, now()->addHour());

        $read = count($snapshot->positions);
        Log::info("Vehicle feed of {$network} recorded.", ['read' => $read, ...$counts]);
        $this->components->info("{$network}: {$read} read, {$counts['dropped']} dropped as stale, {$counts['changed']} changed, {$counts['removed']} removed.");

        return self::SUCCESS;
    }
}
