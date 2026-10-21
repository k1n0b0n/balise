<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportNetworkCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

#[Signature('network:import {network? : Network key from config/networks.php; every network when left out}')]
#[Description('Import the lines and stops of a network from its static catalogue')]
final class ImportNetwork extends Command
{
    public function handle(ImportNetworkCatalog $import): int
    {
        $networks = array_keys(Config::array('networks'));
        $network = $this->argument('network');

        if ($network !== null && (! is_string($network) || ! in_array($network, $networks, true))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', $networks).'.');

            return self::INVALID;
        }

        foreach ($network === null ? $networks : [$network] as $key) {
            $counts = $import->handle($key);

            if ($counts === null) {
                $this->components->info("{$key}: catalogue unchanged since the last import.");
            } else {
                $this->components->info("{$key}: {$counts['lines']} lines and {$counts['stops']} stops imported.");
            }
        }

        return self::SUCCESS;
    }
}
