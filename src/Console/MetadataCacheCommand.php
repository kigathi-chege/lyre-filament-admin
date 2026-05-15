<?php

namespace Lyre\Filament\Admin\Console;

use Illuminate\Console\Command;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;
use Lyre\Filament\Admin\Metadata\MetadataCache;

class MetadataCacheCommand extends Command
{
    protected $signature = 'lyre-admin:metadata:cache';

    protected $description = 'Warm the metadata cache for every discovered model.';

    public function handle(ModelDiscoverer $discoverer, MetadataCache $cache): int
    {
        $descriptors = $discoverer->discover();

        $count = 0;
        foreach ($descriptors as $descriptor) {
            try {
                $cache->get($descriptor->modelClass);
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Skipped {$descriptor->modelClass}: {$e->getMessage()}");
            }
        }

        $this->info("Warmed metadata for {$count} model(s).");

        return self::SUCCESS;
    }
}
