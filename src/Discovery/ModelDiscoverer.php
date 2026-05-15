<?php

namespace Lyre\Filament\Admin\Discovery;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lyre\Filament\Admin\Support\LyreModelDetector;

class ModelDiscoverer
{
    /**
     * @return ModelDescriptor[]
     */
    public function discover(?Panel $panel = null): array
    {
        $namespaces = $this->namespaces();
        $modelClasses = $this->collectModelClasses($namespaces);

        $include = (array) config('lyre-filament-admin.discovery.include', []);
        $exclude = (array) config('lyre-filament-admin.discovery.exclude', []);

        $modelClasses = array_filter($modelClasses, function (string $class) use ($include, $exclude) {
            if ($include !== [] && ! $this->matches($class, $include)) {
                return false;
            }

            if ($this->matches($class, $exclude)) {
                return false;
            }

            return true;
        });

        $handWritten = (bool) config('lyre-filament-admin.coexist.respect_hand_written', true);
        $handWrittenNamespaces = (array) config('lyre-filament-admin.coexist.handwritten_namespaces', ['App\\Filament\\Resources']);

        $panelResources = $this->panelResourcesByModel($panel);

        $descriptors = [];
        foreach ($modelClasses as $modelClass) {
            $isLyre = LyreModelDetector::isLyre($modelClass);

            try {
                $instance = new $modelClass;
            } catch (\Throwable) {
                continue;
            }

            if (! $instance instanceof Model) {
                continue;
            }

            $table = $instance->getTable();
            $singular = Str::headline(class_basename($modelClass));
            $plural = Str::pluralStudly($singular);

            $navigationGroup = config("lyre-filament-admin.navigation.group_map.{$modelClass}");

            $handWrittenClass = null;
            if ($handWritten) {
                $handWrittenClass = $panelResources[ltrim($modelClass, '\\')] ?? null;

                if ($handWrittenClass === null) {
                    $handWrittenClass = $this->detectHandWritten($modelClass, $handWrittenNamespaces);
                }
            }

            $descriptors[] = new ModelDescriptor(
                modelClass: $modelClass,
                table: $table,
                isLyreCompatible: $isLyre,
                hasHandWrittenResource: $handWrittenClass !== null,
                handWrittenResourceClass: $handWrittenClass,
                singularLabel: $singular,
                pluralLabel: $plural,
                slug: Str::kebab(class_basename($modelClass)),
                navigationGroup: $navigationGroup,
            );
        }

        usort($descriptors, fn (ModelDescriptor $a, ModelDescriptor $b) => $a->modelClass <=> $b->modelClass);

        return $descriptors;
    }

    /**
     * @return string[]
     */
    private function namespaces(): array
    {
        $configured = config('lyre-filament-admin.discovery.namespaces');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        $lyrePaths = (array) config('lyre.path.model', []);

        if ($lyrePaths !== []) {
            return $lyrePaths;
        }

        return ['App\\Models'];
    }

    /**
     * @param  string[]  $namespaces
     * @return string[]
     */
    private function collectModelClasses(array $namespaces): array
    {
        $classes = [];

        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');

            if (function_exists('get_model_classes')) {
                try {
                    $found = (array) get_model_classes($namespace);
                    foreach ($found as $value) {
                        if (is_string($value) && class_exists($value)) {
                            $classes[$value] = true;
                        }
                    }

                    continue;
                } catch (\Throwable) {
                    // fall through to fallback
                }
            }

            foreach ($this->scanNamespace($namespace) as $class) {
                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    /**
     * @return string[]
     */
    private function scanNamespace(string $namespace): array
    {
        $namespace = trim($namespace, '\\');
        $base = app_path(str_replace(['App\\', '\\'], ['', DIRECTORY_SEPARATOR], $namespace));

        if (! is_dir($base)) {
            return [];
        }

        $found = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$base.DIRECTORY_SEPARATOR, '/', '.php'], ['', '\\', ''], $file->getPathname());
            $fqcn = $namespace.'\\'.$relative;

            if (! class_exists($fqcn)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($fqcn);
            } catch (\Throwable) {
                continue;
            }

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $found[] = $fqcn;
        }

        return $found;
    }

    /**
     * @param  string[]  $patterns
     */
    private function matches(string $class, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $namespaces
     */
    private function detectHandWritten(string $modelClass, array $namespaces): ?string
    {
        $basename = class_basename($modelClass);

        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            $candidate = $namespace.'\\'.$basename.'Resource';

            if (class_exists($candidate)) {
                if ($this->resourceMatchesModel($candidate, $modelClass)) {
                    return $candidate;
                }
            }

            $clusterCandidates = $this->scanResourcesNamespaceForModel($namespace, $modelClass);
            if ($clusterCandidates !== null) {
                return $clusterCandidates;
            }
        }

        return null;
    }

    private function resourceMatchesModel(string $resourceClass, string $modelClass): bool
    {
        try {
            if (! method_exists($resourceClass, 'getModel')) {
                return false;
            }

            $value = $resourceClass::getModel();

            return is_string($value) && ltrim($value, '\\') === ltrim($modelClass, '\\');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string> map of model FQCN (no leading slash) → resource FQCN
     */
    private function panelResourcesByModel(?Panel $panel): array
    {
        if ($panel === null) {
            return [];
        }

        $runtimeNamespace = trim(
            (string) config('lyre-filament-admin.runtime.namespace', 'Lyre\\Filament\\Admin\\Runtime\\Generated'),
            '\\'
        ).'\\';

        $map = [];

        try {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_string($resourceClass) || ! method_exists($resourceClass, 'getModel')) {
                    continue;
                }

                if (str_starts_with(ltrim($resourceClass, '\\'), $runtimeNamespace)) {
                    continue;
                }

                try {
                    $modelClass = $resourceClass::getModel();
                } catch (\Throwable) {
                    continue;
                }

                if (! is_string($modelClass)) {
                    continue;
                }

                $map[ltrim($modelClass, '\\')] = $resourceClass;
            }
        } catch (\Throwable) {
            return $map;
        }

        return $map;
    }

    private function scanResourcesNamespaceForModel(string $namespace, string $modelClass): ?string
    {
        $base = base_path(str_replace(
            ['App\\', '\\'],
            ['app'.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
            $namespace
        ));

        if (! is_dir($base)) {
            return null;
        }

        $expected = class_basename($modelClass).'Resource.php';

        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getFilename() !== $expected) {
                continue;
            }

            $relative = str_replace([$base.DIRECTORY_SEPARATOR, '/', '.php'], ['', '\\', ''], $file->getPathname());
            $candidate = $namespace.'\\'.$relative;

            if (class_exists($candidate) && $this->resourceMatchesModel($candidate, $modelClass)) {
                return $candidate;
            }
        }

        return null;
    }
}
