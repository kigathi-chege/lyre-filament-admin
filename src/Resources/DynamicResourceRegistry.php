<?php

namespace Lyre\Filament\Admin\Resources;

class DynamicResourceRegistry
{
    /**
     * @var array<string, string>
     */
    private array $byModel = [];

    public function bind(string $modelClass, string $resourceFqcn): void
    {
        $this->byModel[$modelClass] = $resourceFqcn;
    }

    public function forModel(string $modelClass): ?string
    {
        return $this->byModel[$modelClass] ?? null;
    }

    /**
     * @return string[]
     */
    public function resources(): array
    {
        return array_values($this->byModel);
    }

    /**
     * @return array<string, string>
     */
    public function map(): array
    {
        return $this->byModel;
    }
}
