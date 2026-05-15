<?php

namespace Lyre\Filament\Admin\Tests\Unit\Metadata;

use Lyre\Filament\Admin\Metadata\ColumnMetadata;
use Lyre\Filament\Admin\Metadata\ModelMetadata;
use Lyre\Filament\Admin\Metadata\RelationshipMetadata;
use Lyre\Filament\Admin\Support\FieldTypeInference;
use PHPUnit\Framework\TestCase;

class ModelMetadataValueObjectTest extends TestCase
{
    public function test_is_fillable_respects_fillable_array(): void
    {
        $metadata = $this->metadataWithFillableGuarded(['name', 'email'], []);

        $this->assertTrue($metadata->isFillable('name'));
        $this->assertFalse($metadata->isFillable('password'));
    }

    public function test_is_fillable_respects_empty_guarded(): void
    {
        $metadata = $this->metadataWithFillableGuarded([], []);

        $this->assertTrue($metadata->isFillable('whatever'));
    }

    public function test_is_fillable_respects_guarded(): void
    {
        $metadata = $this->metadataWithFillableGuarded([], ['admin_flag']);

        $this->assertFalse($metadata->isFillable('admin_flag'));
        $this->assertTrue($metadata->isFillable('name'));
    }

    public function test_find_belongs_to_for_column(): void
    {
        $metadata = new ModelMetadata(
            modelClass: 'App\\Models\\Foo',
            table: 'foos',
            connection: null,
            primaryKey: 'id',
            keyType: 'int',
            incrementing: true,
            timestamps: true,
            softDeletes: false,
            fillable: [],
            guarded: [],
            hidden: [],
            casts: [],
            columns: [],
            relationships: [
                'author' => new RelationshipMetadata(
                    name: 'author',
                    type: RelationshipMetadata::TYPE_BELONGS_TO,
                    relatedModel: 'App\\Models\\User',
                    foreignKey: 'author_id',
                ),
            ],
            lyreConfig: null,
            displayColumn: 'id',
            isLyreCompatible: false,
        );

        $this->assertNotNull($metadata->findBelongsToForColumn('author_id'));
        $this->assertNull($metadata->findBelongsToForColumn('missing_id'));
    }

    public function test_editable_columns_excludes_system_and_hidden(): void
    {
        $columns = [
            'id' => new ColumnMetadata(
                name: 'id',
                dbType: 'bigint',
                nullable: false,
                default: null,
                length: null,
                isPrimary: true,
                isForeign: false,
                isTimestamp: false,
                isSoftDelete: false,
                cast: null,
                inferredType: FieldTypeInference::TYPE_ID,
                isSystemManaged: true,
            ),
            'name' => new ColumnMetadata(
                name: 'name',
                dbType: 'varchar',
                nullable: false,
                default: null,
                length: 255,
                isPrimary: false,
                isForeign: false,
                isTimestamp: false,
                isSoftDelete: false,
                cast: null,
                inferredType: FieldTypeInference::TYPE_STRING,
                isSystemManaged: false,
            ),
            'remember_token' => new ColumnMetadata(
                name: 'remember_token',
                dbType: 'varchar',
                nullable: true,
                default: null,
                length: 100,
                isPrimary: false,
                isForeign: false,
                isTimestamp: false,
                isSoftDelete: false,
                cast: null,
                inferredType: FieldTypeInference::TYPE_STRING,
                isSystemManaged: false,
            ),
        ];

        $metadata = new ModelMetadata(
            modelClass: 'App\\Models\\Foo',
            table: 'foos',
            connection: null,
            primaryKey: 'id',
            keyType: 'int',
            incrementing: true,
            timestamps: true,
            softDeletes: false,
            fillable: ['name'],
            guarded: [],
            hidden: ['remember_token'],
            casts: [],
            columns: $columns,
            relationships: [],
            lyreConfig: null,
            displayColumn: 'name',
            isLyreCompatible: false,
        );

        $editable = $metadata->editableColumns(forCreate: true);
        $names = array_map(fn (ColumnMetadata $c) => $c->name, $editable);

        $this->assertSame(['name'], $names);
    }

    private function metadataWithFillableGuarded(array $fillable, array $guarded): ModelMetadata
    {
        return new ModelMetadata(
            modelClass: 'App\\Models\\Foo',
            table: 'foos',
            connection: null,
            primaryKey: 'id',
            keyType: 'int',
            incrementing: true,
            timestamps: true,
            softDeletes: false,
            fillable: $fillable,
            guarded: $guarded,
            hidden: [],
            casts: [],
            columns: [],
            relationships: [],
            lyreConfig: null,
            displayColumn: 'id',
            isLyreCompatible: false,
        );
    }
}
