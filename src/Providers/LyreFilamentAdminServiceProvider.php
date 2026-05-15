<?php

namespace Lyre\Filament\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Lyre\Filament\Admin\Authorization\AuthorizationPipeline;
use Lyre\Filament\Admin\Builders\DynamicFilterBuilder;
use Lyre\Filament\Admin\Builders\DynamicFormBuilder;
use Lyre\Filament\Admin\Builders\DynamicInfolistBuilder;
use Lyre\Filament\Admin\Builders\DynamicTableBuilder;
use Lyre\Filament\Admin\Console\DoctorCommand;
use Lyre\Filament\Admin\Console\InstallCommand;
use Lyre\Filament\Admin\Console\ListCommand;
use Lyre\Filament\Admin\Console\MetadataCacheCommand;
use Lyre\Filament\Admin\Console\MetadataClearCommand;
use Lyre\Filament\Admin\Console\RebuildFilamentCacheCommand;
use Lyre\Filament\Admin\Console\ShowCommand;
use Lyre\Filament\Admin\Discovery\ModelDiscoverer;
use Lyre\Filament\Admin\Discovery\ModelRegistry;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadataResolver;
use Lyre\Filament\Admin\Metadata\SchemaIntrospector;
use Lyre\Filament\Admin\Relationships\DynamicRelationManagerFactory;
use Lyre\Filament\Admin\Relationships\RelationshipResolver;
use Lyre\Filament\Admin\Resources\DynamicResourceFactory;
use Lyre\Filament\Admin\Resources\DynamicResourceRegistry;
use Lyre\Filament\Admin\Runtime\RuntimeAutoloader;
use Lyre\Filament\Admin\Runtime\RuntimeClassFactory;
use Lyre\Filament\Admin\Runtime\RuntimeClassRegistry;

class LyreFilamentAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lyre-filament-admin.php', 'lyre-filament-admin');

        $this->app->singleton(SchemaIntrospector::class);
        $this->app->singleton(ModelMetadataResolver::class);
        $this->app->singleton(MetadataCache::class);
        $this->app->singleton(ModelDiscoverer::class);
        $this->app->singleton(ModelRegistry::class);
        $this->app->singleton(RuntimeClassRegistry::class);
        $this->app->singleton(RuntimeClassFactory::class);
        $this->app->singleton(RuntimeAutoloader::class);
        $this->app->singleton(DynamicResourceRegistry::class);
        $this->app->singleton(DynamicResourceFactory::class);
        $this->app->singleton(RelationshipResolver::class);
        $this->app->singleton(DynamicRelationManagerFactory::class);
        $this->app->singleton(AuthorizationPipeline::class);
        $this->app->singleton(DynamicFormBuilder::class);
        $this->app->singleton(DynamicTableBuilder::class);
        $this->app->singleton(DynamicInfolistBuilder::class);
        $this->app->singleton(DynamicFilterBuilder::class);

        $this->app->resolving(RuntimeAutoloader::class, fn (RuntimeAutoloader $autoloader) => $autoloader->register());
        $this->app->make(RuntimeAutoloader::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/lyre-filament-admin.php' => config_path('lyre-filament-admin.php'),
        ], 'lyre-filament-admin-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ListCommand::class,
                ShowCommand::class,
                MetadataCacheCommand::class,
                MetadataClearCommand::class,
                RebuildFilamentCacheCommand::class,
                DoctorCommand::class,
            ]);
        }
    }
}
