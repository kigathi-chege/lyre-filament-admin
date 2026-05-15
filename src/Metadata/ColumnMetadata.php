<?php

namespace Lyre\Filament\Admin\Metadata;

use Lyre\Filament\Admin\Support\FieldTypeInference;

final class ColumnMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $dbType,
        public readonly bool $nullable,
        public readonly mixed $default,
        public readonly ?int $length,
        public readonly bool $isPrimary,
        public readonly bool $isForeign,
        public readonly bool $isTimestamp,
        public readonly bool $isSoftDelete,
        public readonly ?string $cast,
        public readonly string $inferredType,
        public readonly bool $isSystemManaged,
        public readonly ?string $foreignTable = null,
        public readonly ?string $foreignColumn = null,
    ) {}

    public function isHiddenByDefault(): bool
    {
        return $this->isSystemManaged
            || $this->inferredType === FieldTypeInference::TYPE_PASSWORD;
    }
}
