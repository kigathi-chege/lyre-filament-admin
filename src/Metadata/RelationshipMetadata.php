<?php

namespace Lyre\Filament\Admin\Metadata;

final class RelationshipMetadata
{
    public const TYPE_BELONGS_TO = 'belongsTo';

    public const TYPE_HAS_ONE = 'hasOne';

    public const TYPE_HAS_MANY = 'hasMany';

    public const TYPE_BELONGS_TO_MANY = 'belongsToMany';

    public const TYPE_MORPH_TO = 'morphTo';

    public const TYPE_MORPH_MANY = 'morphMany';

    public const TYPE_MORPH_ONE = 'morphOne';

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $relatedModel,
        public readonly ?string $foreignKey = null,
        public readonly ?string $localKey = null,
        public readonly ?string $ownerKey = null,
    ) {}

    public function isPolymorphic(): bool
    {
        return in_array($this->type, [self::TYPE_MORPH_TO, self::TYPE_MORPH_MANY, self::TYPE_MORPH_ONE], true);
    }

    public function isSingular(): bool
    {
        return in_array($this->type, [self::TYPE_BELONGS_TO, self::TYPE_HAS_ONE, self::TYPE_MORPH_ONE, self::TYPE_MORPH_TO], true);
    }
}
