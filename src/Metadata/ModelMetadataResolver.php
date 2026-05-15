<?php

namespace Lyre\Filament\Admin\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lyre\Filament\Admin\Support\FieldTypeInference;
use Lyre\Filament\Admin\Support\LyreModelDetector;

class ModelMetadataResolver
{
    public function __construct(
        private readonly SchemaIntrospector $schema,
    ) {}

    public function resolve(string $modelClass): ModelMetadata
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("[{$modelClass}] is not an Eloquent model.");
        }

        /** @var Model $instance */
        $instance = new $modelClass;

        $table = $instance->getTable();
        $connection = $instance->getConnectionName();
        $primaryKey = $instance->getKeyName();
        $keyType = $instance->getKeyType();
        $incrementing = $instance->getIncrementing();
        $timestamps = $instance->usesTimestamps();
        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);

        $fillable = $instance->getFillable();
        $guarded = $instance->getGuarded();
        $hidden = $instance->getHidden();
        $casts = $instance->getCasts();

        $columns = $this->buildColumns($modelClass, $instance, $table, $connection, $casts, $timestamps, $softDeletes, $primaryKey);

        $isLyre = LyreModelDetector::isLyre($modelClass);
        $lyreConfig = null;

        if ($isLyre && method_exists($modelClass, 'generateConfig')) {
            try {
                $lyreConfig = $modelClass::generateConfig();
            } catch (\Throwable $e) {
                $lyreConfig = null;
            }
        }

        $relationships = $this->resolveRelationships($modelClass, $isLyre);

        $displayColumn = $this->resolveDisplayColumn($columns, $lyreConfig, $primaryKey);

        return new ModelMetadata(
            modelClass: $modelClass,
            table: $table,
            connection: $connection,
            primaryKey: $primaryKey,
            keyType: $keyType,
            incrementing: $incrementing,
            timestamps: $timestamps,
            softDeletes: $softDeletes,
            fillable: $fillable,
            guarded: $guarded,
            hidden: $hidden,
            casts: $casts,
            columns: $columns,
            relationships: $relationships,
            lyreConfig: $lyreConfig,
            displayColumn: $displayColumn,
            isLyreCompatible: $isLyre,
        );
    }

    /**
     * @return array<string, ColumnMetadata>
     */
    private function buildColumns(
        string $modelClass,
        Model $instance,
        string $table,
        ?string $connection,
        array $casts,
        bool $timestamps,
        bool $softDeletes,
        string $primaryKey,
    ): array {
        $rawColumns = $this->schema->columns($table, $connection);
        $foreignKeys = $this->schema->foreignKeys($table, $connection);

        $createdAt = $timestamps ? $instance->getCreatedAtColumn() : null;
        $updatedAt = $timestamps ? $instance->getUpdatedAtColumn() : null;
        $deletedAt = $softDeletes && defined("{$modelClass}::DELETED_AT")
            ? $modelClass::DELETED_AT
            : ($softDeletes ? 'deleted_at' : null);

        $result = [];

        foreach ($rawColumns as $name => $info) {
            $dbType = $info['type'] ?? null;
            $nullable = (bool) ($info['nullable'] ?? true);
            $default = $info['default'] ?? null;
            $length = $info['length'] ?? null;

            $isPrimary = $name === $primaryKey;
            $isForeign = array_key_exists($name, $foreignKeys);
            $isTimestamp = $name === $createdAt || $name === $updatedAt;
            $isSoftDelete = $name === $deletedAt;

            $cast = $casts[$name] ?? null;

            $inferred = FieldTypeInference::infer(
                columnName: $name,
                dbType: $dbType,
                cast: is_string($cast) ? $cast : null,
                isPrimary: $isPrimary,
                isForeign: $isForeign,
                isTimestamp: $isTimestamp,
                isSoftDelete: $isSoftDelete,
            );

            $isSystem = $isPrimary || $isTimestamp || $isSoftDelete;

            $result[$name] = new ColumnMetadata(
                name: $name,
                dbType: is_string($dbType) ? $dbType : (is_array($dbType) ? ($dbType['name'] ?? null) : null),
                nullable: $nullable,
                default: $default,
                length: is_int($length) ? $length : null,
                isPrimary: $isPrimary,
                isForeign: $isForeign,
                isTimestamp: $isTimestamp,
                isSoftDelete: $isSoftDelete,
                cast: is_string($cast) ? $cast : null,
                inferredType: $inferred,
                isSystemManaged: $isSystem,
                foreignTable: $foreignKeys[$name]['table'] ?? null,
                foreignColumn: $foreignKeys[$name]['column'] ?? null,
            );
        }

        return $result;
    }

    /**
     * @return array<string, RelationshipMetadata>
     */
    public function resolveRelationships(string $modelClass, bool $useLyreCache = true): array
    {
        if ($useLyreCache && method_exists($modelClass, 'getModelRelationships')) {
            $lyreMap = [];

            try {
                $lyreMap = (array) $modelClass::getModelRelationships(1);
            } catch (\Throwable $e) {
                $lyreMap = [];
            }

            if ($lyreMap !== []) {
                return $this->classifyRelationships($modelClass, array_keys($lyreMap));
            }
        }

        return $this->classifyRelationships($modelClass, $this->discoverRelationMethodNames($modelClass));
    }

    /**
     * @return string[]
     */
    private function discoverRelationMethodNames(string $modelClass): array
    {
        $names = [];

        try {
            $reflection = new \ReflectionClass($modelClass);
        } catch (\Throwable) {
            return [];
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === Model::class
                || $method->getDeclaringClass()->isAbstract()
                || $method->getNumberOfRequiredParameters() > 0
                || $method->isStatic()
                || str_starts_with($method->getName(), '__')
            ) {
                continue;
            }

            if (in_array($method->getDeclaringClass()->getName(), [
                Model::class,
                'Lyre\\Model',
            ], true)) {
                continue;
            }

            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType
                && ! $returnType->isBuiltin()
                && is_subclass_of($returnType->getName(), Relation::class)) {
                $names[] = $method->getName();

                continue;
            }

            if ($returnType === null && $method->getDeclaringClass()->getName() === $modelClass) {
                $names[] = $method->getName();
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  string[]  $methodNames
     * @return array<string, RelationshipMetadata>
     */
    private function classifyRelationships(string $modelClass, array $methodNames): array
    {
        $instance = new $modelClass;
        $relations = [];

        foreach ($methodNames as $name) {
            try {
                $invocation = $instance->{$name}();
            } catch (\Throwable) {
                continue;
            }

            if (! $invocation instanceof Relation) {
                continue;
            }

            $type = $this->classifyRelationType($invocation);
            $relatedModel = null;
            $foreignKey = null;
            $localKey = null;
            $ownerKey = null;

            try {
                $relatedModel = get_class($invocation->getRelated());
            } catch (\Throwable) {
            }

            if ($invocation instanceof BelongsTo) {
                try {
                    $foreignKey = $invocation->getForeignKeyName();
                    $ownerKey = $invocation->getOwnerKeyName();
                } catch (\Throwable) {
                }
            } elseif ($invocation instanceof HasOne || $invocation instanceof HasMany) {
                try {
                    $foreignKey = $invocation->getForeignKeyName();
                    $localKey = $invocation->getLocalKeyName();
                } catch (\Throwable) {
                }
            }

            $relations[$name] = new RelationshipMetadata(
                name: $name,
                type: $type,
                relatedModel: $relatedModel,
                foreignKey: $foreignKey,
                localKey: $localKey,
                ownerKey: $ownerKey,
            );
        }

        return $relations;
    }

    private function classifyRelationType(Relation $relation): string
    {
        return match (true) {
            $relation instanceof MorphTo => RelationshipMetadata::TYPE_MORPH_TO,
            $relation instanceof MorphOne => RelationshipMetadata::TYPE_MORPH_ONE,
            $relation instanceof MorphMany => RelationshipMetadata::TYPE_MORPH_MANY,
            $relation instanceof BelongsTo => RelationshipMetadata::TYPE_BELONGS_TO,
            $relation instanceof BelongsToMany => RelationshipMetadata::TYPE_BELONGS_TO_MANY,
            $relation instanceof HasOne => RelationshipMetadata::TYPE_HAS_ONE,
            $relation instanceof HasMany => RelationshipMetadata::TYPE_HAS_MANY,
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, ColumnMetadata>  $columns
     * @param  array<string, mixed>|null  $lyreConfig
     */
    private function resolveDisplayColumn(array $columns, ?array $lyreConfig, string $primaryKey): string
    {
        if (is_array($lyreConfig) && isset($lyreConfig['name']) && isset($columns[$lyreConfig['name']])) {
            return $lyreConfig['name'];
        }

        foreach (['name', 'title', 'label', 'email', 'code', 'slug'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return $primaryKey;
    }
}
