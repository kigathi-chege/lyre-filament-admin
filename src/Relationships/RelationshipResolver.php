<?php

namespace Lyre\Filament\Admin\Relationships;

use Lyre\Filament\Admin\Metadata\MetadataCache;
use Lyre\Filament\Admin\Metadata\RelationshipMetadata;

class RelationshipResolver
{
    public function __construct(
        private readonly MetadataCache $metadataCache,
    ) {}

    /**
     * @return RelationshipMetadata[]
     */
    public function manageableFor(string $modelClass): array
    {
        $metadata = $this->metadataCache->get($modelClass);

        $manageable = [];

        foreach ($metadata->relationships as $relation) {
            if ($relation->relatedModel === null) {
                continue;
            }

            if (in_array($relation->type, [
                RelationshipMetadata::TYPE_HAS_ONE,
                RelationshipMetadata::TYPE_HAS_MANY,
                RelationshipMetadata::TYPE_BELONGS_TO_MANY,
            ], true)) {
                $manageable[] = $relation;

                continue;
            }

            if ($relation->isPolymorphic() && ! (bool) config('lyre-filament-admin.polymorphic.read_only', true)) {
                $manageable[] = $relation;
            } elseif ($relation->isPolymorphic()) {
                $manageable[] = $relation;
            }
        }

        return $manageable;
    }
}
