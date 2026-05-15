<?php

namespace Lyre\Filament\Admin\Console;

use Illuminate\Console\Command;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;
use Lyre\Filament\Admin\Metadata\MetadataCache;

class MetadataClearCommand extends Command
{
    protected $signature = 'lyre-admin:metadata:clear';

    protected $description = 'Clear the metadata cache for every discovered model.';

    public function handle(ModelDiscoverer $discoverer, MetadataCache $cache): int
    {
        $descriptors = $discoverer->discover();

        foreach ($descriptors as $descriptor) {
            try {
                $cache->forget($descriptor->modelClass);
            } catch (\Throwable) {
                // ignore
            }
        }

        $cache->flush();

        $this->info('Lyre admin metadata cache cleared.');

        return self::SUCCESS;
    }
}
