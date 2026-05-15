<?php

namespace Lyre\Filament\Admin\Tests\Unit\Support;

use Lyre\Filament\Admin\Support\FieldTypeInference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldTypeInferenceTest extends TestCase
{
    #[DataProvider('provideCases')]
    public function test_infer(array $args, string $expected): void
    {
        $this->assertSame(
            $expected,
            FieldTypeInference::infer(
                columnName: $args['name'],
                dbType: $args['dbType'] ?? null,
                cast: $args['cast'] ?? null,
                isPrimary: $args['isPrimary'] ?? false,
                isForeign: $args['isForeign'] ?? false,
                isTimestamp: $args['isTimestamp'] ?? false,
                isSoftDelete: $args['isSoftDelete'] ?? false,
            )
        );
    }

    public static function provideCases(): array
    {
        return [
            'primary key wins' => [['name' => 'id', 'dbType' => 'bigint', 'isPrimary' => true], FieldTypeInference::TYPE_ID],
            'soft delete wins' => [['name' => 'deleted_at', 'dbType' => 'timestamp', 'isSoftDelete' => true], FieldTypeInference::TYPE_SOFT_DELETE],
            'timestamp wins' => [['name' => 'created_at', 'dbType' => 'timestamp', 'isTimestamp' => true], FieldTypeInference::TYPE_TIMESTAMP_SYSTEM],
            'foreign key via flag' => [['name' => 'user_id', 'dbType' => 'bigint', 'isForeign' => true], FieldTypeInference::TYPE_FOREIGN_KEY],
            'foreign key via name' => [['name' => 'author_id', 'dbType' => 'bigint'], FieldTypeInference::TYPE_FOREIGN_KEY],
            'email column' => [['name' => 'email', 'dbType' => 'varchar'], FieldTypeInference::TYPE_EMAIL],
            'password column' => [['name' => 'password', 'dbType' => 'varchar'], FieldTypeInference::TYPE_PASSWORD],
            'url-named column' => [['name' => 'website_url', 'dbType' => 'varchar'], FieldTypeInference::TYPE_URL],
            'uuid column' => [['name' => 'uuid', 'dbType' => 'uuid'], FieldTypeInference::TYPE_UUID],
            'boolean cast' => [['name' => 'is_active', 'dbType' => 'tinyint', 'cast' => 'boolean'], FieldTypeInference::TYPE_BOOLEAN],
            'json cast' => [['name' => 'meta', 'dbType' => 'jsonb', 'cast' => 'array'], FieldTypeInference::TYPE_JSON],
            'integer dbtype' => [['name' => 'count', 'dbType' => 'bigint'], FieldTypeInference::TYPE_INTEGER],
            'float dbtype' => [['name' => 'price', 'dbType' => 'decimal'], FieldTypeInference::TYPE_FLOAT],
            'datetime dbtype' => [['name' => 'starts_at', 'dbType' => 'timestamp'], FieldTypeInference::TYPE_DATETIME],
            'date dbtype' => [['name' => 'birthday', 'dbType' => 'date'], FieldTypeInference::TYPE_DATE],
            'text dbtype' => [['name' => 'body', 'dbType' => 'text'], FieldTypeInference::TYPE_TEXT],
            'string dbtype' => [['name' => 'title', 'dbType' => 'varchar'], FieldTypeInference::TYPE_STRING],
            'json dbtype' => [['name' => 'config', 'dbType' => 'jsonb'], FieldTypeInference::TYPE_JSON],
        ];
    }
}
