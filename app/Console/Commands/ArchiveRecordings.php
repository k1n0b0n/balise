<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\FeedRecordings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

#[Signature('network:archive {network : Network key from config/networks.php}')]
#[Description('Archive the finished service days of a network\'s recordings')]
final class ArchiveRecordings extends Command
{
    public function handle(FeedRecordings $recordings): int
    {
        $network = $this->argument('network');

        if (! array_key_exists($network, Config::array('networks'))) {
            $this->components->error('Unknown network. Configured networks: '.implode(', ', array_keys(Config::array('networks'))).'.');

            return self::INVALID;
        }

        $days = $recordings->archive($network, CarbonImmutable::now());
        $this->components->info($days === [] ? "{$network}: no finished service day to archive." : "{$network}: archived ".implode(', ', $days).'.');

        return self::SUCCESS;
    }
}
