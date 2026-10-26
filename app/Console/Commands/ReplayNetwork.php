<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportNetworkCatalog;
use App\Ingestion\NetworkCatalogs;
use App\Support\Replays;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Throwable;

#[Signature('network:replay
    {network : Network key from config/networks.php}
    {day? : Recorded service day (YYYY-MM-DD); the latest one when left out}
    {--from=12:00 : Time of that day to start from (HH:MM)}
    {--stop : Go back to the live feed and its catalogue}')]
#[Description('Replay a recorded service day of a network, downloaded from the site\'s recordings')]
final class ReplayNetwork extends Command
{
    public function handle(Replays $replays, NetworkCatalogs $catalogs, ImportNetworkCatalog $import): int
    {
        $network = $this->argument('network');

        if (! array_key_exists($network, Config::array('networks'))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', array_keys(Config::array('networks'))).'.');

            return self::INVALID;
        }

        // The production site shows the live network, never a replay.
        if (app()->isProduction()) {
            $this->components->error('Replays run locally only.');

            return self::FAILURE;
        }

        if ($this->option('stop')) {
            // The live catalogue first: when its import fails, the replay goes on and --stop can be run again.
            $import->handle($network);
            $replays->stop($network);
            $this->components->info("{$network}: back to the live feed and its catalogue.");

            return self::SUCCESS;
        }

        $from = (string) $this->option('from');
        $day = $this->argument('day');

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) !== 1 || ($day !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1)) {
            $this->components->error('The day is YYYY-MM-DD and --from is HH:MM.');

            return self::INVALID;
        }

        try {
            if ($day === null || ! $replays->has($network, $day)) {
                $day = $replays->download($network, $day);
            }

            $import->replaceWith($network, $catalogs->fromFile($network, $replays->directory($network, $day).'/catalog.zip'));
        } catch (Throwable $exception) {
            $this->components->error("{$network}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $replays->start($network, $day, $from, CarbonImmutable::now());
        $this->components->info("{$network}: replaying {$day} from {$from}; `network:replay {$network} --stop` goes back to the live feed.");

        return self::SUCCESS;
    }
}
