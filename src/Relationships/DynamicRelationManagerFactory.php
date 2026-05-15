<?php

namespace Lyre\Filament\Admin\Relationships;

use Lyre\Filament\Admin\Runtime\RuntimeClassFactory;

class DynamicRelationManagerFactory
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $cache = [];

    public function __construct(
        private readonly RelationshipResolver $resolver,
        private readonly RuntimeClassFactory $runtime,
    ) {}

    /**
     * @return array<int, string>
     */
    public function relationsFor(string $modelClass): array
    {
        if (isset($this->cache[$modelClass])) {
            return $this->cache[$modelClass];
        }

        $managers = [];

        foreach ($this->resolver->manageableFor($modelClass) as $relation) {
            if ($relation->relatedModel === null) {
                continue;
            }

            $managers[] = $this->runtime->generateRelationManager(
                ownerModelClass: $modelClass,
                relationName: $relation->name,
                relatedModelClass: $relation->relatedModel,
            );
        }

        return $this->cache[$modelClass] = $managers;
    }
}
