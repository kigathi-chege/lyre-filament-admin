<?php

namespace Lyre\Filament\Admin\Builders;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Lyre\Filament\Admin\Metadata\ColumnMetadata;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Metadata\RelationshipMetadata;
use Lyre\Filament\Admin\Support\FieldTypeInference;

class DynamicFilterBuilder
{
    /**
     * @return array<int, mixed>
     */
    public function build(ModelMetadata $metadata): array
    {
        $filters = [];

        foreach ($metadata->columns as $column) {
            $filter = $this->filterFor($metadata, $column);
            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        if ($metadata->softDeletes) {
            $filters[] = TrashedFilter::make();
        }

        return $filters;
    }

    private function filterFor(ModelMetadata $metadata, ColumnMetadata $column): mixed
    {
        if ($column->inferredType === FieldTypeInference::TYPE_BOOLEAN) {
            return TernaryFilter::make($column->name);
        }

        if ($column->inferredType === FieldTypeInference::TYPE_FOREIGN_KEY) {
            $relation = $metadata->findBelongsToForColumn($column->name);
            if (! $relation instanceof RelationshipMetadata || $relation->relatedModel === null) {
                return null;
            }

            $display = $this->relatedDisplayColumn($relation->relatedModel);

            return SelectFilter::make($column->name)
                ->relationship($relation->name, $display)
                ->searchable()
                ->preload();
        }

        if ($column->inferredType === FieldTypeInference::TYPE_ENUM && is_string($column->cast) && enum_exists($column->cast)) {
            $options = [];
            foreach ($column->cast::cases() as $case) {
                $value = property_exists($case, 'value') ? $case->value : $case->name;
                $options[$value] = ucfirst(strtolower((string) $value));
            }

            return SelectFilter::make($column->name)->options($options);
        }

        return null;
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
