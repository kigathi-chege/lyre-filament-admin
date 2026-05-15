<?php

namespace Lyre\Filament\Admin\Discovery;

final class ModelDescriptor
{
    public function __construct(
        public readonly string $modelClass,
        public readonly string $table,
        public readonly bool $isLyreCompatible,
        public readonly bool $hasHandWrittenResource,
        public readonly ?string $handWrittenResourceClass,
        public readonly string $singularLabel,
        public readonly string $pluralLabel,
        public readonly string $slug,
        public readonly ?string $navigationGroup,
    ) {}
}
