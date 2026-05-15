<?php

namespace Lyre\Filament\Admin\Resources;

use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lyre\Filament\Admin\Authorization\AuthorizationPipeline;
use Lyre\Filament\Admin\Builders\DynamicFormBuilder;
use Lyre\Filament\Admin\Builders\DynamicInfolistBuilder;
use Lyre\Filament\Admin\Builders\DynamicTableBuilder;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Relationships\DynamicRelationManagerFactory;
use Lyre\Filament\Admin\Runtime\RuntimeClassRegistry;
use Lyre\Filament\Admin\Support\FieldTypeInference;

abstract class BaseDynamicResource extends Resource
{
    protected static bool $isDiscovered = false;

    protected static string $metadataKey = '';

    protected static string $resourceKey = '';

    public static function metadata(): ModelMetadata
    {
        return app(MetadataCache::class)->get(static::$metadataKey);
    }

    public static function getModelLabel(): string
    {
        return Str::headline(class_basename(static::getModel()));
    }

    public static function getPluralModelLabel(): string
    {
        return Str::pluralStudly(static::getModelLabel());
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return static::metadata()->displayColumn;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('lyre-filament-admin.navigation.group_map.'.static::getModel());
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return config('lyre-filament-admin.navigation.icon_default', 'heroicon-o-cube');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return Str::kebab(class_basename(static::getModel()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(
            app(DynamicFormBuilder::class)->build(static::metadata(), forCreate: $schema->getContainer()?->getName() === 'create')
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(
            app(DynamicInfolistBuilder::class)->build(static::metadata())
        );
    }

    public static function table(Table $table): Table
    {
        return app(DynamicTableBuilder::class)->apply(static::metadata(), $table);
    }

    public static function getEloquentQuery(): Builder
    {
        $metadata = static::metadata();

        if (config('lyre-filament-admin.use_repository', true)
            && $metadata->lyreConfig
            && isset($metadata->lyreConfig['repository-interface'])
            && interface_exists($metadata->lyreConfig['repository-interface'])
        ) {
            try {
                $repository = app($metadata->lyreConfig['repository-interface']);
                if (method_exists($repository, 'getQuery')) {
                    $query = $repository->getQuery();
                    if ($query instanceof Builder) {
                        return $query;
                    }
                }
            } catch (\Throwable) {
                // fall through to default
            }
        }

        return parent::getEloquentQuery();
    }

    public static function getRelations(): array
    {
        return app(DynamicRelationManagerFactory::class)->relationsFor(static::getModel());
    }

    public static function getPages(): array
    {
        $registry = app(RuntimeClassRegistry::class);
        $classes = $registry->classesFor(static::getModel());

        $pages = [];

        if (isset($classes['list'])) {
            $pages['index'] = $classes['list']::route('/');
        }
        if (isset($classes['create'])) {
            $pages['create'] = $classes['create']::route('/create');
        }
        if (isset($classes['view'])) {
            $pages['view'] = $classes['view']::route('/{record}');
        }
        if (isset($classes['edit'])) {
            $pages['edit'] = $classes['edit']::route('/{record}/edit');
        }

        return $pages;
    }

    public static function canViewAny(): bool
    {
        return static::authorizeAbility('viewAny');
    }

    public static function canCreate(): bool
    {
        return static::authorizeAbility('create');
    }

    public static function canView(Model $record): bool
    {
        return static::authorizeAbility('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return static::authorizeAbility('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::authorizeAbility('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::authorizeAbility('deleteAny');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::authorizeAbility('forceDelete', $record);
    }

    public static function canRestore(Model $record): bool
    {
        return static::authorizeAbility('restore', $record);
    }

    public static function canGloballySearch(): bool
    {
        return static::canViewAny()
            && filled(static::getGloballySearchableAttributes());
    }

    public static function getGloballySearchableAttributes(): array
    {
        $metadata = static::metadata();
        $attributes = [$metadata->displayColumn];

        foreach ($metadata->columns as $column) {
            if (in_array($column->inferredType, [
                FieldTypeInference::TYPE_STRING,
                FieldTypeInference::TYPE_EMAIL,
            ], true) && ! $column->isSystemManaged) {
                $attributes[] = $column->name;
            }
        }

        return array_values(array_unique($attributes));
    }

    protected static function authorizeAbility(string $ability, ?Model $record = null): bool
    {
        return app(AuthorizationPipeline::class)->check(static::getModel(), $ability, $record);
    }
}
