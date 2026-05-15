<?php

namespace Lyre\Filament\Admin\Console;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;

class ListCommand extends Command
{
    protected $signature = 'lyre-admin:list {--panel=admin}';

    protected $description = 'List models discovered by the Lyre Filament Admin engine and how they will be exposed.';

    public function handle(ModelDiscoverer $discoverer): int
    {
        $panelId = (string) $this->option('panel');
        $panel = null;

        try {
            $panel = Filament::getPanel($panelId, isStrict: false);
        } catch (\Throwable) {
            $panel = null;
        }

        $descriptors = $discoverer->discover($panel);

        $rows = [];
        foreach ($descriptors as $descriptor) {
            $status = $descriptor->hasHandWrittenResource
                ? 'skipped:hand-written'
                : 'dynamic';

            $resource = $descriptor->hasHandWrittenResource
                ? ($descriptor->handWrittenResourceClass ?? '?')
                : '(runtime)';

            $rows[] = [
                $descriptor->modelClass,
                $descriptor->isLyreCompatible ? 'yes' : 'no',
                $status,
                $resource,
            ];
        }

        $this->table(['Model', 'Lyre', 'Status', 'Resource'], $rows);

        $this->info(sprintf('Discovered %d model(s).', count($descriptors)));

        return self::SUCCESS;
    }
}
