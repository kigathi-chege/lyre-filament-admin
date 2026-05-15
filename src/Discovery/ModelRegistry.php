<?php

namespace Lyre\Filament\Admin\Discovery;

class ModelRegistry
{
    /**
     * @var array<string, ModelDescriptor>
     */
    private array $descriptors = [];

    /**
     * @var array<string, string>
     */
    private array $resourceByModel = [];

    /**
     * @param  array<int, ModelDescriptor>  $descriptors
     */
    public function hydrate(array $descriptors): void
    {
        $this->descriptors = [];
        foreach ($descriptors as $descriptor) {
            $this->descriptors[$descriptor->modelClass] = $descriptor;
        }
    }

    public function bindResource(string $modelClass, string $resourceFqcn): void
    {
        $this->resourceByModel[$modelClass] = $resourceFqcn;
    }

    /**
     * @return ModelDescriptor[]
     */
    public function all(): array
    {
        return array_values($this->descriptors);
    }

    public function descriptor(string $modelClass): ?ModelDescriptor
    {
        return $this->descriptors[$modelClass] ?? null;
    }

    public function resourceFor(string $modelClass): ?string
    {
        return $this->resourceByModel[$modelClass] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function dynamicResources(): array
    {
        return $this->resourceByModel;
    }
}
