<?php

namespace Lyre\Filament\Admin\Builders;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Lyre\Filament\Admin\Metadata\ColumnMetadata;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Support\FieldTypeInference;

class DynamicInfolistBuilder
{
    /**
     * @return array<int, mixed>
     */
    public function build(ModelMetadata $metadata): array
    {
        $primary = [];
        $system = [];

        foreach ($metadata->columns as $column) {
            $entry = $this->makeEntry($column);
            if ($entry === null) {
                continue;
            }

            if ($column->isSystemManaged) {
                $system[] = $entry;
            } else {
                $primary[] = $entry;
            }
        }

        $components = [];

        if ($primary !== []) {
            $components[] = Section::make('Details')->schema($primary)->columns(2);
        }

        if ($system !== []) {
            $components[] = Section::make('System')->schema($system)->columns(2)->collapsed();
        }

        return $components;
    }

    private function makeEntry(ColumnMetadata $column): mixed
    {
        return match ($column->inferredType) {
            FieldTypeInference::TYPE_BOOLEAN => IconEntry::make($column->name)->boolean(),
            FieldTypeInference::TYPE_DATE => TextEntry::make($column->name)->date(),
            FieldTypeInference::TYPE_DATETIME, FieldTypeInference::TYPE_TIMESTAMP_SYSTEM, FieldTypeInference::TYPE_SOFT_DELETE => TextEntry::make($column->name)->dateTime(),
            FieldTypeInference::TYPE_JSON => TextEntry::make($column->name)
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : (string) $state),
            FieldTypeInference::TYPE_PASSWORD => null,
            FieldTypeInference::TYPE_INTEGER, FieldTypeInference::TYPE_FLOAT => TextEntry::make($column->name)->numeric(),
            FieldTypeInference::TYPE_EMAIL => TextEntry::make($column->name)->icon('heroicon-m-envelope')->copyable(),
            FieldTypeInference::TYPE_URL => TextEntry::make($column->name)->url(fn ($record) => $record->{$column->name})->openUrlInNewTab(),
            FieldTypeInference::TYPE_ENUM => TextEntry::make($column->name)->badge(),
            default => TextEntry::make($column->name),
        };
    }
}
