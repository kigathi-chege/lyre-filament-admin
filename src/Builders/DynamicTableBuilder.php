<?php

namespace Lyre\Filament\Admin\Builders;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lyre\Filament\Admin\Metadata\ColumnMetadata;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Metadata\RelationshipMetadata;
use Lyre\Filament\Admin\Support\FieldTypeInference;

class DynamicTableBuilder
{
    public function apply(ModelMetadata $metadata, Table $table): Table
    {
        $table
            ->columns($this->columns($metadata))
            ->filters(app(DynamicFilterBuilder::class)->build($metadata))
            ->recordActions(array_filter([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]))
            ->defaultSort(
                $metadata->timestamps ? 'created_at' : $metadata->primaryKey,
                'desc'
            )
            ->defaultPaginationPageOption(
                (int) config('lyre-filament-admin.tables.default_per_page', 25)
            );

        if (config('lyre-filament-admin.tables.bulk_actions', true)) {
            $bulkActions = [DeleteBulkAction::make()];

            if ($metadata->softDeletes) {
                $bulkActions[] = RestoreBulkAction::make();
                $bulkActions[] = ForceDeleteBulkAction::make();
            }

            $table->toolbarActions([BulkActionGroup::make($bulkActions)]);
        }

        return $table;
    }

    /**
     * @return array<int, mixed>
     */
    public function columns(ModelMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->columns as $column) {
            $built = $this->makeColumn($metadata, $column);
            if ($built !== null) {
                $columns[] = $built;
            }
        }

        return $columns;
    }

    private function makeColumn(ModelMetadata $metadata, ColumnMetadata $column): mixed
    {
        return match ($column->inferredType) {
            FieldTypeInference::TYPE_ID => TextColumn::make($column->name)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            FieldTypeInference::TYPE_FOREIGN_KEY => $this->foreignKeyColumn($metadata, $column),
            FieldTypeInference::TYPE_BOOLEAN => IconColumn::make($column->name)->boolean(),
            FieldTypeInference::TYPE_DATE => TextColumn::make($column->name)->date()->sortable(),
            FieldTypeInference::TYPE_DATETIME => TextColumn::make($column->name)->dateTime()->sortable(),
            FieldTypeInference::TYPE_TIMESTAMP_SYSTEM => TextColumn::make($column->name)
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            FieldTypeInference::TYPE_SOFT_DELETE => TextColumn::make($column->name)
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            FieldTypeInference::TYPE_INTEGER, FieldTypeInference::TYPE_FLOAT => TextColumn::make($column->name)
                ->numeric()
                ->sortable(),
            FieldTypeInference::TYPE_JSON => TextColumn::make($column->name)
                ->limit(40)
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state),
            FieldTypeInference::TYPE_TEXT => TextColumn::make($column->name)
                ->limit(60)
                ->wrap()
                ->searchable(),
            FieldTypeInference::TYPE_EMAIL => TextColumn::make($column->name)
                ->icon('heroicon-m-envelope')
                ->copyable()
                ->searchable(),
            FieldTypeInference::TYPE_URL => TextColumn::make($column->name)
                ->url(fn ($record) => $record->{$column->name})
                ->openUrlInNewTab()
                ->limit(40),
            FieldTypeInference::TYPE_ENUM => TextColumn::make($column->name)
                ->badge()
                ->formatStateUsing(fn ($state) => is_object($state) && property_exists($state, 'value')
                    ? (string) $state->value
                    : (string) $state),
            FieldTypeInference::TYPE_PASSWORD => null,
            FieldTypeInference::TYPE_UUID => TextColumn::make($column->name)
                ->copyable()
                ->limit(12)
                ->toggleable(isToggledHiddenByDefault: true),
            FieldTypeInference::TYPE_STRING, FieldTypeInference::TYPE_UNKNOWN => TextColumn::make($column->name)
                ->searchable()
                ->sortable()
                ->limit(50),
            default => null,
        };
    }

    private function foreignKeyColumn(ModelMetadata $metadata, ColumnMetadata $column): mixed
    {
        $relation = $metadata->findBelongsToForColumn($column->name);

        if ($relation instanceof RelationshipMetadata && $relation->relatedModel !== null) {
            $display = $this->relatedDisplayColumn($relation->relatedModel);

            return TextColumn::make($relation->name.'.'.$display)
                ->label(Str::headline($relation->name))
                ->searchable()
                ->sortable();
        }

        return TextColumn::make($column->name)
            ->numeric()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private function relatedDisplayColumn(string $relatedModel): string
    {
        try {
            return app(MetadataCache::class)->get($relatedModel)->displayColumn;
        } catch (\Throwable) {
            return 'id';
        }
    }
}
