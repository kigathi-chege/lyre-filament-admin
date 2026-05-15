<?php

namespace Lyre\Filament\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * One-shot bootstrap for the Lyre Filament Admin engine.
 *
 * IMPORTANT: this class deliberately imports NOTHING from `Filament\*`
 * so it remains loadable even when `filament/filament` is uninstalled.
 * It checks for Filament's presence at runtime and exits with guidance
 * when missing.
 */
class InstallCommand extends Command
{
    protected $signature = 'lyre-admin:install
                            {--brand=Aspire : Brand name written into the AdminPanelProvider}
                            {--with-shield : Wire FilamentShieldPlugin into the AdminPanelProvider}
                            {--force : Overwrite an existing AdminPanelProvider without backing it up}
                            {--skip-cache-rebuild : Skip the final Filament cache rebuild}';

    protected $description = 'Bootstrap the Lyre Filament Admin engine: publish config, write a clean AdminPanelProvider, ensure runtime directories, and refresh caches.';

    public function handle(): int
    {
        $this->info('Lyre Filament Admin — install');
        $this->newLine();

        if (! $this->checkFilamentInstalled()) {
            return self::FAILURE;
        }

        $this->publishConfigIfMissing();
        $this->ensureRuntimeDirectory();
        $this->writeAdminPanelProvider();
        $this->registerProviderInBootstrap();
        $this->clearFilamentCaches();

        if (! $this->option('skip-cache-rebuild')) {
            $this->rebuildFilamentCache();
        }

        $this->newLine();
        $this->info('Lyre Filament Admin is installed.');
        $this->line('Next steps:');
        $this->line('  • Visit /admin in your browser.');
        $this->line('  • `php artisan lyre-admin:doctor` to verify environment.');
        $this->line('  • `php artisan lyre-admin:list` to see which models are dynamic.');

        return self::SUCCESS;
    }

    private function checkFilamentInstalled(): bool
    {
        if (class_exists(\Filament\PanelProvider::class)
            && class_exists(\Filament\Panel::class)) {
            $this->line('  ✔ filament/filament present.');

            return true;
        }

        $this->error('filament/filament is not installed.');
        $this->newLine();
        $this->line('Install it first:');
        $this->line('  <fg=cyan>composer require filament/filament:^5.0</>');
        $this->newLine();
        $this->line('Then re-run:');
        $this->line('  <fg=cyan>php artisan lyre-admin:install</>');

        return false;
    }

    private function publishConfigIfMissing(): void
    {
        $configPath = config_path('lyre-filament-admin.php');

        if (File::exists($configPath)) {
            $this->line('  • Config already published at config/lyre-filament-admin.php');

            return;
        }

        $this->line('  • Publishing config...');
        Artisan::call('vendor:publish', [
            '--tag' => 'lyre-filament-admin-config',
            '--force' => false,
        ]);
        $this->line('    ✔ Published config/lyre-filament-admin.php');
    }

    private function ensureRuntimeDirectory(): void
    {
        $runtime = function_exists('lyre_admin_runtime_path')
            ? lyre_admin_runtime_path()
            : storage_path('framework/lyre-admin');

        if (! File::isDirectory($runtime)) {
            File::makeDirectory($runtime, 0775, true);
            $this->line("  ✔ Created runtime directory: {$runtime}");
        } else {
            $this->line("  • Runtime directory exists: {$runtime}");
        }

        $gitignore = $runtime.DIRECTORY_SEPARATOR.'.gitignore';
        if (! File::exists($gitignore)) {
            File::put($gitignore, "*\n!.gitignore\n");
        }
    }

    private function writeAdminPanelProvider(): void
    {
        $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
        $providerDir = dirname($providerPath);

        if (! File::isDirectory($providerDir)) {
            File::makeDirectory($providerDir, 0775, true);
        }

        if (File::exists($providerPath) && ! $this->option('force')) {
            $backup = $providerPath.'.backup-'.date('Ymd-His');
            File::copy($providerPath, $backup);
            $this->line('  • Existing AdminPanelProvider backed up to '.basename($backup));
        }

        $stub = File::get(__DIR__.'/stubs/AdminPanelProvider.stub');

        $shieldImport = '';
        $shieldPlugin = '';

        if ($this->option('with-shield') && class_exists(\BezhanSalleh\FilamentShield\FilamentShieldPlugin::class)) {
            $shieldImport = "use BezhanSalleh\\FilamentShield\\FilamentShieldPlugin;\n";
            $shieldPlugin = "                FilamentShieldPlugin::make(),\n";
        }

        $rendered = strtr($stub, [
            '{{BRAND}}' => addslashes((string) $this->option('brand')),
            '{{SHIELD_IMPORT}}' => $shieldImport,
            '{{SHIELD_PLUGIN}}' => $shieldPlugin,
        ]);

        File::put($providerPath, $rendered);
        $this->line('  ✔ Wrote app/Providers/Filament/AdminPanelProvider.php');
    }

    private function registerProviderInBootstrap(): void
    {
        $bootstrapPath = base_path('bootstrap/providers.php');

        if (! File::exists($bootstrapPath)) {
            $this->warn('  ! bootstrap/providers.php not found — register AdminPanelProvider manually.');

            return;
        }

        $contents = File::get($bootstrapPath);
        $entry = 'App\\Providers\\Filament\\AdminPanelProvider::class';

        if (str_contains($contents, $entry)) {
            $this->line('  • bootstrap/providers.php already references AdminPanelProvider');

            return;
        }

        $updated = preg_replace(
            '/return\s*\[/',
            "return [\n    App\\Providers\\Filament\\AdminPanelProvider::class,",
            $contents,
            1
        );

        if ($updated === null || $updated === $contents) {
            $this->warn('  ! Could not auto-register AdminPanelProvider in bootstrap/providers.php — add it manually.');

            return;
        }

        File::put($bootstrapPath, $updated);
        $this->line('  ✔ Registered AdminPanelProvider in bootstrap/providers.php');
    }

    private function clearFilamentCaches(): void
    {
        try {
            Artisan::call('filament:clear-cached-components');
            $this->line('  ✔ Cleared Filament component cache.');
        } catch (\Throwable $e) {
            $this->line('  • No Filament component cache to clear.');
        }

        try {
            Artisan::call('lyre-admin:metadata:clear');
            $this->line('  ✔ Cleared Lyre admin metadata cache.');
        } catch (\Throwable) {
            // not fatal
        }
    }

    private function rebuildFilamentCache(): void
    {
        $this->line('  • Skipping `filament:cache-components` by default. Use `--skip-cache-rebuild=false` only in deployments.');
    }
}
