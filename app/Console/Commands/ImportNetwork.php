<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportNetworkCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

#[Signature('network:import {network : Network key from config/networks.php}')]
#[Description('Import the lines and stops of a network from its static catalogue')]
final class ImportNetwork extends Command
{
    public function handle(ImportNetworkCatalog $import): int
    {
        $network = $this->argument('network');

        if (! is_string($network) || ! array_key_exists($network, Config::array('networks'))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', array_keys(Config::array('networks'))).'.');

            return self::INVALID;
        }

        $counts = $import->handle($network);

        if ($counts === null) {
            $this->components->info("{$network}: catalogue unchanged since the last import.");

            return self::SUCCESS;
        }

        $this->components->info("{$network}: {$counts['lines']} lines and {$counts['stops']} stops imported.");

        return self::SUCCESS;
    }
}
