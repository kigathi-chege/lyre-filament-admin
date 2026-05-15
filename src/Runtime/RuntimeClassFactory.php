<?php

namespace Lyre\Filament\Admin\Runtime;

use Lyre\Filament\Admin\Support\StableHash;

class RuntimeClassFactory
{
    private const KIND_RESOURCE = 'resource';

    private const KIND_LIST = 'list';

    private const KIND_CREATE = 'create';

    private const KIND_EDIT = 'edit';

    private const KIND_VIEW = 'view';

    private const KIND_RELATION = 'relation';

    public function __construct(
        private readonly RuntimeClassRegistry $registry,
    ) {}

    public function ensureDirectory(): string
    {
        $dir = lyre_admin_runtime_path();

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @return array{
     *     resource: string,
     *     list: string,
     *     create: string,
     *     edit: string,
     *     view: string,
     * }
     */
    public function generateResourceFamily(string $modelClass, string $resourceKey): array
    {
        $resourceFqcn = $this->generateResource($modelClass, $resourceKey);
        $list = $this->generatePage($modelClass, self::KIND_LIST, 'ListPage', $resourceFqcn);
        $create = $this->generatePage($modelClass, self::KIND_CREATE, 'CreatePage', $resourceFqcn);
        $edit = $this->generatePage($modelClass, self::KIND_EDIT, 'EditPage', $resourceFqcn);
        $view = $this->generatePage($modelClass, self::KIND_VIEW, 'ViewPage', $resourceFqcn);

        return [
            'resource' => $resourceFqcn,
            'list' => $list,
            'create' => $create,
            'edit' => $edit,
            'view' => $view,
        ];
    }

    public function generateResource(string $modelClass, string $resourceKey): string
    {
        $className = StableHash::generatedClassName($modelClass, 'Resource');
        $namespace = lyre_admin_runtime_namespace();
        $fqcn = $namespace.'\\'.$className;

        if (class_exists($fqcn, false)) {
            $this->ensureRegistered($modelClass, self::KIND_RESOURCE, $fqcn);

            return $fqcn;
        }

        $contents = $this->renderStub('Resource.stub', [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASSNAME}}' => $className,
            '{{MODEL_FQCN}}' => $modelClass,
            '{{METADATA_KEY}}' => $modelClass,
            '{{RESOURCE_KEY}}' => $resourceKey,
        ]);

        $path = $this->writeFile($className, $contents);
        $this->registry->register($modelClass, self::KIND_RESOURCE, $fqcn, $path);

        return $fqcn;
    }

    public function generatePage(string $modelClass, string $kind, string $suffix, string $resourceFqcn): string
    {
        $className = StableHash::generatedClassName($modelClass, $suffix);
        $namespace = lyre_admin_runtime_namespace();
        $fqcn = $namespace.'\\'.$className;

        if (class_exists($fqcn, false)) {
            $this->ensureRegistered($modelClass, $kind, $fqcn);

            return $fqcn;
        }

        $stub = match ($kind) {
            self::KIND_LIST => 'ListPage.stub',
            self::KIND_CREATE => 'CreatePage.stub',
            self::KIND_EDIT => 'EditPage.stub',
            self::KIND_VIEW => 'ViewPage.stub',
            default => throw new \InvalidArgumentException("Unknown page kind: {$kind}"),
        };

        $contents = $this->renderStub($stub, [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASSNAME}}' => $className,
            '{{RESOURCE_FQCN}}' => $resourceFqcn,
        ]);

        $path = $this->writeFile($className, $contents);
        $this->registry->register($modelClass, $kind, $fqcn, $path);

        return $fqcn;
    }

    public function generateRelationManager(
        string $ownerModelClass,
        string $relationName,
        string $relatedModelClass,
    ): string {
        $className = StableHash::generatedClassName($ownerModelClass, 'Rel'.ucfirst($relationName).'Manager');
        $namespace = lyre_admin_runtime_namespace();
        $fqcn = $namespace.'\\'.$className;

        $kindKey = self::KIND_RELATION.':'.$relationName;

        if (class_exists($fqcn, false)) {
            $this->ensureRegistered($ownerModelClass, $kindKey, $fqcn);

            return $fqcn;
        }

        $contents = $this->renderStub('RelationManager.stub', [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASSNAME}}' => $className,
            '{{RELATIONSHIP}}' => $relationName,
            '{{RELATED_MODEL_FQCN}}' => $relatedModelClass,
            '{{METADATA_KEY}}' => $relatedModelClass,
        ]);

        $path = $this->writeFile($className, $contents);
        $this->registry->register($ownerModelClass, $kindKey, $fqcn, $path);

        return $fqcn;
    }

    private function ensureRegistered(string $modelClass, string $kind, string $fqcn): void
    {
        if ($this->registry->fileFor($fqcn) !== null) {
            return;
        }

        $className = substr($fqcn, strrpos($fqcn, '\\') + 1);
        $this->registry->register($modelClass, $kind, $fqcn, $this->pathFor($className));
    }

    private function renderStub(string $stubName, array $replacements): string
    {
        $stubPath = __DIR__.DIRECTORY_SEPARATOR.'Stubs'.DIRECTORY_SEPARATOR.$stubName;
        $template = file_get_contents($stubPath);

        if ($template === false) {
            throw new \RuntimeException("Could not read stub: {$stubPath}");
        }

        return strtr($template, $replacements);
    }

    private function pathFor(string $className): string
    {
        return $this->ensureDirectory().DIRECTORY_SEPARATOR.$className.'.php';
    }

    private function writeFile(string $className, string $contents): string
    {
        $finalPath = $this->pathFor($className);
        $tmpPath = $finalPath.'.tmp.'.bin2hex(random_bytes(4));

        if (file_put_contents($tmpPath, $contents, LOCK_EX) === false) {
            throw new \RuntimeException("Failed writing runtime file: {$tmpPath}");
        }

        if (! @rename($tmpPath, $finalPath)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed renaming runtime file to: {$finalPath}");
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($finalPath, true);
        }

        return $finalPath;
    }
}
