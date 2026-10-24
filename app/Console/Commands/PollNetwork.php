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
use Illuminate\Support\Sleep;
use Throwable;

#[Signature('network:poll {network : Network key from config/networks.php}')]
#[Description('Fetch the live vehicle positions of a network')]
final class PollNetwork extends Command
{
    /** A feed is served about a second after the time it carries (TBM, measured over two hours, story 024). */
    public const int PUBLICATION_DELAY_SECONDS = 1;

    /** An unchanged feed is asked again once, this much later: the provider may be a little late. */
    public const int RETRY_AFTER_SECONDS = 2;

    public function handle(VehicleFeeds $feeds, RecordPositions $record): int
    {
        $network = $this->argument('network');

        if (! array_key_exists($network, Config::array('networks'))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', array_keys(Config::array('networks'))).'.');

            return self::INVALID;
        }

        $etagKey = "vehicle-feed:{$network}:etag";
        $publishedKey = "vehicle-feed:{$network}:published-at";
        $etag = Cache::get($etagKey);
        $feed = $feeds->for($network);

        $publishedAt = Cache::get($publishedKey);
        $this->waitForPublication(Config::integer("networks.{$network}.vehicles.poll_every_seconds"), is_int($publishedAt) ? $publishedAt : null);
        $retried = false;

        try {
            $snapshot = $feed->fetch(is_string($etag) ? $etag : null);
        } catch (Throwable $exception) {
            // The current positions stay, but vehicles still age out: a feed down for an hour must not leave them on
            // the map (E2.3). The next scheduled run retries.
            $record->handle($network, [], CarbonImmutable::now());
            Log::warning("Vehicle feed of {$network} could not be read.", ['exception' => $exception]);
            $this->components->error("{$network}: feed unavailable ({$exception->getMessage()}).");

            return self::FAILURE;
        }

        if ($snapshot === null) {
            $retried = true;
            Sleep::for(self::RETRY_AFTER_SECONDS)->seconds();

            try {
                $snapshot = $feed->fetch(is_string($etag) ? $etag : null);
            } catch (Throwable $exception) {
                // The first answer said the feed had not changed: carry on as for that answer.
                Log::info("Vehicle feed of {$network} could not be read again.", ['exception' => $exception]);
            }
        }

        $receivedAt = CarbonImmutable::now();

        if ($snapshot === null) {
            // Nothing new, but vehicles that stopped reporting still age out.
            $counts = $record->handle($network, [], $receivedAt);
            $this->components->info("{$network}: feed unchanged, {$counts['removed']} removed.");

            return self::SUCCESS;
        }

        $counts = $record->handle($network, $snapshot->positions, $receivedAt);
        Cache::put($etagKey, $snapshot->etag, now()->addHour());

        // How late the feed was read, so the server's logs measure what the waiting gains.
        $lag = [];
        if ($snapshot->publishedAt !== null) {
            Cache::put($publishedKey, $snapshot->publishedAt->getTimestamp(), now()->addHour());
            $lag = ['seconds_after_publication' => round($receivedAt->getTimestamp() + $receivedAt->micro / 1e6 - $snapshot->publishedAt->getTimestamp(), 1)];
        }

        $read = count($snapshot->positions);
        Log::info("Vehicle feed of {$network} recorded.", ['read' => $read, ...$counts, ...$lag, 'retried' => $retried]);
        $this->components->info("{$network}: {$read} read, {$counts['dropped']} dropped as stale, {$counts['changed']} changed, {$counts['removed']} removed.");

        return self::SUCCESS;
    }

    /**
     * Waits until just after the feed's next publication. Providers publish at a fixed second of their interval (TBM
     * at :02, :22 and :42), which the scheduler's runs (:00, :20, :40) knew nothing of: a run read the feed published
     * 18 s before. The second is learnt from the last feed's own time, so a provider that moves it is followed.
     *
     * It assumes that the provider publishes at the poll's interval, that both clocks are synchronised (NTP: a server
     * ahead of the provider asks too early and retries, which `retried` shows in the logs), and one network per
     * scheduler: the scheduler runs polls one after another, so the waits of several networks would add up.
     */
    private function waitForPublication(int $everySeconds, ?int $publishedAt): void
    {
        if ($publishedAt === null) {
            return;
        }

        $now = CarbonImmutable::now();
        $sinceAvailable = fmod($now->getTimestamp() + $now->micro / 1e6 - $publishedAt - self::PUBLICATION_DELAY_SECONDS, $everySeconds);
        $sinceAvailable += $sinceAvailable < 0 ? $everySeconds : 0;

        // Served less than half an interval ago: read it now rather than wait for the next one.
        if ($sinceAvailable > $everySeconds / 2) {
            Sleep::for((int) round(($everySeconds - $sinceAvailable) * 1000))->milliseconds();
        }
    }
}
