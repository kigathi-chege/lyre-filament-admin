<?php

namespace Lyre\Filament\Admin\Metadata;

use Lyre\Filament\Admin\Support\FieldTypeInference;

final class ModelMetadata
{
    /**
     * @param  array<string, ColumnMetadata>  $columns
     * @param  array<string, RelationshipMetadata>  $relationships
     * @param  array<string, mixed>|null  $lyreConfig
     * @param  array<string, mixed>  $casts
     * @param  string[]  $fillable
     * @param  string[]  $guarded
     * @param  string[]  $hidden
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $table,
        public readonly ?string $connection,
        public readonly string $primaryKey,
        public readonly string $keyType,
        public readonly bool $incrementing,
        public readonly bool $timestamps,
        public readonly bool $softDeletes,
        public readonly array $fillable,
        public readonly array $guarded,
        public readonly array $hidden,
        public readonly array $casts,
        public readonly array $columns,
        public readonly array $relationships,
        public readonly ?array $lyreConfig,
        public readonly string $displayColumn,
        public readonly bool $isLyreCompatible,
    ) {}

    public function column(string $name): ?ColumnMetadata
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * @return array<string, RelationshipMetadata>
     */
    public function relationshipsByType(string $type): array
    {
        return array_filter(
            $this->relationships,
            fn (RelationshipMetadata $r) => $r->type === $type
        );
    }

    public function findBelongsToForColumn(string $foreignKey): ?RelationshipMetadata
    {
        foreach ($this->relationshipsByType(RelationshipMetadata::TYPE_BELONGS_TO) as $rel) {
            if ($rel->foreignKey === $foreignKey) {
                return $rel;
            }
        }

        return null;
    }

    public function isFillable(string $column): bool
    {
        if (! empty($this->fillable)) {
            return in_array($column, $this->fillable, true);
        }

        return $this->guarded === [] || ! in_array($column, $this->guarded, true);
    }

    /**
     * @return ColumnMetadata[]
     */
    public function editableColumns(bool $forCreate = true): array
    {
        return array_values(array_filter(
            $this->columns,
            function (ColumnMetadata $c) use ($forCreate) {
                if ($c->isSystemManaged) {
                    return false;
                }

                if (in_array($c->name, $this->hidden, true)) {
                    return false;
                }

                if ($c->inferredType === FieldTypeInference::TYPE_PASSWORD && ! $forCreate) {
                    return true;
                }

                return $this->isFillable($c->name);
            }
        ));
    }
}
