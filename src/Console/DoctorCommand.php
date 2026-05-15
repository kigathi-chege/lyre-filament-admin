<?php

namespace Lyre\Filament\Admin\Console;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;

class DoctorCommand extends Command
{
    protected $signature = 'lyre-admin:doctor {--panel=admin}';

    protected $description = 'Run environment diagnostics for Lyre Filament Admin.';

    public function handle(ModelDiscoverer $discoverer): int
    {
        $panelId = (string) $this->option('panel');
        $panel = null;

        try {
            $panel = Filament::getPanel($panelId, isStrict: false);
        } catch (\Throwable) {
            $panel = null;
        }

        $this->info('Lyre Filament Admin — doctor');
        $this->newLine();

        $runtimeDir = lyre_admin_runtime_path();
        $writable = is_dir($runtimeDir) ? is_writable($runtimeDir) : is_writable(dirname($runtimeDir));
        $this->line('Runtime dir: '.$runtimeDir.($writable ? ' [writable]' : ' [NOT WRITABLE]'));

        $cacheDir = base_path('bootstrap/cache/filament');
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir.DIRECTORY_SEPARATOR.'*-components.php') ?: [];
            foreach ($files as $file) {
                $contains = str_contains((string) @file_get_contents($file), 'Lyre\\Filament\\Admin\\Runtime\\Generated\\');
                $this->line('Filament cache: '.$file.($contains ? ' [includes runtime resources]' : ' [STALE — run lyre-admin:rebuild-filament-cache]'));
            }
        } else {
            $this->line('Filament cache: (none)');
        }

        $shield = (bool) config('lyre.filament-shield', false);
        $this->line('Shield enabled: '.($shield ? 'yes' : 'no'));

        $fallback = (string) config('lyre-filament-admin.authorization.fallback', 'deny');
        $this->line('Authorization fallback: '.$fallback);

        $descriptors = $discoverer->discover($panel);
        $dynamic = array_values(array_filter($descriptors, fn ($d) => ! $d->hasHandWrittenResource));
        $skipped = array_values(array_filter($descriptors, fn ($d) => $d->hasHandWrittenResource));

        $this->newLine();
        $this->line('Models discovered: '.count($descriptors));
        $this->line('  dynamic: '.count($dynamic));
        $this->line('  hand-written (skipped): '.count($skipped));

        return self::SUCCESS;
    }
}
