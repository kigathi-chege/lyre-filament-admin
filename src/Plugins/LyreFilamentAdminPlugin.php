<?php

namespace Lyre\Filament\Admin\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;
use Lyre\Filament\Admin\Discovery\ModelRegistry;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Resources\DynamicResourceFactory;

class LyreFilamentAdminPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'lyre-filament-admin';
    }

    public function register(Panel $panel): void
    {
        if (! config('lyre-filament-admin.enabled', true)) {
            return;
        }

        $this->assertFilamentCacheIsFresh($panel);

        /** @var ModelDiscoverer $discoverer */
        $discoverer = app(ModelDiscoverer::class);
        $descriptors = $discoverer->discover($panel);

        /** @var ModelRegistry $registry */
        $registry = app(ModelRegistry::class);
        $registry->hydrate($descriptors);

        $factory = app(DynamicResourceFactory::class);
        $metadataCache = app(MetadataCache::class);

        $resources = [];

        foreach ($descriptors as $descriptor) {
            if ($descriptor->hasHandWrittenResource && config('lyre-filament-admin.coexist.respect_hand_written', true)) {
                continue;
            }

            try {
                $metadataCache->get($descriptor->modelClass);
            } catch (\Throwable) {
                continue;
            }

            $resourceFqcn = $factory->produce($descriptor);
            $registry->bindResource($descriptor->modelClass, $resourceFqcn);
            $resources[] = $resourceFqcn;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    private function assertFilamentCacheIsFresh(Panel $panel): void
    {
        if (! method_exists($panel, 'hasCachedComponents') || ! $panel->hasCachedComponents()) {
            return;
        }

        $cachePath = $this->resolveCachePath($panel);

        if ($cachePath === null || ! is_file($cachePath)) {
            return;
        }

        $contents = file_get_contents($cachePath) ?: '';

        if (! str_contains($contents, 'Lyre\\Filament\\Admin\\Runtime\\Generated\\')) {
            throw new \RuntimeException(
                "Filament's component cache (`{$cachePath}`) is stale and would override Lyre dynamic resources. "
                .'Run `php artisan lyre-admin:rebuild-filament-cache` to refresh it, '
                .'or `php artisan filament:clear-cached-components` to disable the cache for now.'
            );
        }
    }

    private function resolveCachePath(Panel $panel): ?string
    {
        if (! method_exists($panel, 'getComponentCachePath')) {
            return null;
        }

        try {
            $reflection = new \ReflectionMethod($panel, 'getComponentCachePath');

            return (string) $reflection->invoke($panel);
        } catch (\Throwable) {
            return null;
        }
    }
}
