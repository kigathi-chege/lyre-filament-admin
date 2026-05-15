<?php

namespace Lyre\Filament\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RebuildFilamentCacheCommand extends Command
{
    protected $signature = 'lyre-admin:rebuild-filament-cache';

    protected $description = 'Clear and rebuild the Filament component cache after Lyre dynamic resources are registered.';

    public function handle(): int
    {
        $this->info('Clearing existing Filament component cache...');

        try {
            Artisan::call('filament:clear-cached-components');
            $this->line(trim(Artisan::output()));
        } catch (\Throwable $e) {
            $this->warn('Could not clear Filament cache: '.$e->getMessage());
        }

        $this->info('Rebuilding Filament component cache (this also picks up Lyre runtime resources)...');

        try {
            Artisan::call('filament:cache-components');
            $this->line(trim(Artisan::output()));
        } catch (\Throwable $e) {
            $this->error('Failed rebuilding Filament cache: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
