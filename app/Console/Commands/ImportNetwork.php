<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportNetworkCatalog;
use App\Support\Replays;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Throwable;

#[Signature('network:import {network? : Network key from config/networks.php; every network when left out}')]
#[Description('Import the lines and stops of a network from its static catalogue')]
final class ImportNetwork extends Command
{
    public function handle(ImportNetworkCatalog $import, Replays $replays): int
    {
        $networks = array_keys(Config::array('networks'));
        $network = $this->argument('network');

        if ($network !== null && ! in_array($network, $networks, true)) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', $networks).'.');

            return self::INVALID;
        }

        if ($network !== null) {
            $this->import($import, $replays, $network);

            return self::SUCCESS;
        }

        // One provider that is down must not keep the other networks from importing.
        $failed = false;

        foreach ($networks as $key) {
            try {
                $this->import($import, $replays, $key);
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("{$key}: {$exception->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function import(ImportNetworkCatalog $import, Replays $replays, string $network): void
    {
        // A replay keeps its day's catalogue, so that its lines match (E5.2).
        $replay = $replays->current($network);
        if ($replay !== null) {
            $this->components->info("{$network}: replaying {$replay['day']}, its catalogue is kept.");

            return;
        }

        $counts = $import->handle($network);

        if ($counts === null) {
            $this->components->info("{$network}: catalogue unchanged since the last import.");
        } else {
            $this->components->info("{$network}: {$counts['lines']} lines and {$counts['stops']} stops imported.");
        }
    }
}
