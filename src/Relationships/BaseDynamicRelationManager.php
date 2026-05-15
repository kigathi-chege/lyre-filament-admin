<?php

namespace Lyre\Filament\Admin\Relationships;

use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Lyre\Filament\Admin\Builders\DynamicFormBuilder;
use Lyre\Filament\Admin\Builders\DynamicInfolistBuilder;
use Lyre\Filament\Admin\Builders\DynamicTableBuilder;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadata;

abstract class BaseDynamicRelationManager extends RelationManager
{
    protected static ?string $relatedModel = null;

    protected static string $metadataKey = '';

    public static function getRelatedModelClass(): string
    {
        return static::$relatedModel ?? static::$metadataKey;
    }

    public static function metadata(): ModelMetadata
    {
        return app(MetadataCache::class)->get(static::getRelatedModelClass());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(
            app(DynamicFormBuilder::class)->build(static::metadata(), forCreate: true)
        );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components(
            app(DynamicInfolistBuilder::class)->build(static::metadata())
        );
    }

    public function table(Table $table): Table
    {
        $table = app(DynamicTableBuilder::class)->apply(static::metadata(), $table);

        if (! $this->isReadOnly()) {
            $table->headerActions([CreateAction::make()]);
        }

        return $table;
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
