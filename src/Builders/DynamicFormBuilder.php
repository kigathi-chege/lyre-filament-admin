<?php

namespace Lyre\Filament\Admin\Builders;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Lyre\Filament\Admin\Metadata\ColumnMetadata;
use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Metadata\RelationshipMetadata;
use Lyre\Filament\Admin\Support\FieldTypeInference;

class DynamicFormBuilder
{
    /**
     * @return array<int, mixed>
     */
    public function build(ModelMetadata $metadata, bool $forCreate = true): array
    {
        $components = [];

        foreach ($metadata->columns as $column) {
            if (! $this->shouldRender($metadata, $column, $forCreate)) {
                continue;
            }

            $component = $this->makeComponent($metadata, $column, $forCreate);
            if ($component !== null) {
                $components[] = $component;
            }
        }

        return $components;
    }

    private function shouldRender(ModelMetadata $metadata, ColumnMetadata $column, bool $forCreate): bool
    {
        if (in_array($column->name, $metadata->hidden, true) && $column->inferredType !== FieldTypeInference::TYPE_PASSWORD) {
            return false;
        }

        if ($column->isSystemManaged) {
            if ($forCreate) {
                return false;
            }

            return (bool) config('lyre-filament-admin.forms.show_system_fields_on_edit', false);
        }

        return $metadata->isFillable($column->name);
    }

    private function makeComponent(ModelMetadata $metadata, ColumnMetadata $column, bool $forCreate): mixed
    {
        $required = ! $column->nullable && $column->default === null && ! $column->isSystemManaged;

        return match ($column->inferredType) {
            FieldTypeInference::TYPE_FOREIGN_KEY => $this->foreignKeySelect($metadata, $column),
            FieldTypeInference::TYPE_BOOLEAN => Toggle::make($column->name),
            FieldTypeInference::TYPE_DATE => DatePicker::make($column->name)->native(false)->required($required),
            FieldTypeInference::TYPE_DATETIME => DateTimePicker::make($column->name)->native(false)->required($required),
            FieldTypeInference::TYPE_TEXT => Textarea::make($column->name)->columnSpanFull()->rows(4)->required($required),
            FieldTypeInference::TYPE_JSON => Textarea::make($column->name)
                ->columnSpanFull()
                ->rows(6)
                ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
            FieldTypeInference::TYPE_INTEGER, FieldTypeInference::TYPE_FLOAT => TextInput::make($column->name)
                ->numeric()
                ->required($required),
            FieldTypeInference::TYPE_EMAIL => TextInput::make($column->name)
                ->email()
                ->maxLength(255)
                ->required($required),
            FieldTypeInference::TYPE_URL => TextInput::make($column->name)
                ->url()
                ->maxLength(2048)
                ->required($required),
            FieldTypeInference::TYPE_PASSWORD => TextInput::make($column->name)
                ->password()
                ->revealable()
                ->required(fn () => $forCreate)
                ->dehydrated(fn ($state) => filled($state))
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->maxLength(255),
            FieldTypeInference::TYPE_ENUM => $this->enumSelect($column, $required),
            FieldTypeInference::TYPE_UUID => TextInput::make($column->name)->maxLength(36),
            FieldTypeInference::TYPE_STRING, FieldTypeInference::TYPE_UNKNOWN => TextInput::make($column->name)
                ->maxLength($column->length ?? 255)
                ->required($required),
            default => null,
        };
    }

    private function foreignKeySelect(ModelMetadata $metadata, ColumnMetadata $column): mixed
    {
        $relation = $metadata->findBelongsToForColumn($column->name);

        if ($relation instanceof RelationshipMetadata && $relation->relatedModel !== null) {
            $displayColumn = $this->relatedDisplayColumn($relation->relatedModel);

            return Select::make($relation->name)
                ->relationship($relation->name, $displayColumn)
                ->searchable()
                ->preload()
                ->required(! $column->nullable);
        }

        return TextInput::make($column->name)
            ->numeric()
            ->required(! $column->nullable);
    }

    private function enumSelect(ColumnMetadata $column, bool $required): mixed
    {
        if (! is_string($column->cast) || ! enum_exists($column->cast)) {
            return TextInput::make($column->name);
        }

        $options = [];
        foreach ($column->cast::cases() as $case) {
            $value = property_exists($case, 'value') ? $case->value : $case->name;
            $options[$value] = ucfirst(strtolower((string) $value));
        }

        return Select::make($column->name)
            ->options($options)
            ->required($required);
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
