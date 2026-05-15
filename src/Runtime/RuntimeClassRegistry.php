<?php

namespace Lyre\Filament\Admin\Runtime;

class RuntimeClassRegistry
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $byModel = [];

    /**
     * @var array<string, string>
     */
    private array $classToFile = [];

    /**
     * @var array<string, string>
     */
    private array $classToModel = [];

    public function register(string $modelClass, string $kind, string $fqcn, string $filePath): void
    {
        $this->byModel[$modelClass][$kind] = $fqcn;
        $this->classToFile[$fqcn] = $filePath;
        $this->classToModel[$fqcn] = $modelClass;
    }

    public function fileFor(string $fqcn): ?string
    {
        return $this->classToFile[$fqcn] ?? null;
    }

    public function modelFor(string $fqcn): ?string
    {
        return $this->classToModel[$fqcn] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function classesFor(string $modelClass): array
    {
        return $this->byModel[$modelClass] ?? [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        return $this->byModel;
    }

    /**
     * @return string[]
     */
    public function allResourceFqcns(): array
    {
        $out = [];
        foreach ($this->byModel as $kinds) {
            if (isset($kinds['resource'])) {
                $out[] = $kinds['resource'];
            }
        }

        return $out;
    }
}
