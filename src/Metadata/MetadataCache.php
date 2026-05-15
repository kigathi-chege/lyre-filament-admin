<?php

namespace Lyre\Filament\Admin\Metadata;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class MetadataCache
{
    /**
     * @var array<string, ModelMetadata>
     */
    private array $memo = [];

    public function __construct(
        private readonly ModelMetadataResolver $resolver,
        private readonly SchemaIntrospector $schema,
    ) {}

    public function get(string $modelClass): ModelMetadata
    {
        if (isset($this->memo[$modelClass])) {
            return $this->memo[$modelClass];
        }

        $key = $this->keyFor($modelClass);

        $store = $this->store();

        $cached = $store->get($key);
        if ($cached instanceof ModelMetadata) {
            return $this->memo[$modelClass] = $cached;
        }

        $metadata = $this->resolver->resolve($modelClass);

        $store->forever($key, $metadata);

        return $this->memo[$modelClass] = $metadata;
    }

    public function forget(string $modelClass): void
    {
        unset($this->memo[$modelClass]);
        $this->store()->forget($this->keyFor($modelClass));
    }

    public function flush(): void
    {
        foreach ($this->memo as $class => $_) {
            $this->store()->forget($this->keyFor($class));
        }

        $this->memo = [];
        $this->schema->flush();
    }

    private function keyFor(string $modelClass): string
    {
        $instance = new $modelClass;
        $fingerprint = $this->schema->fingerprint($instance->getTable(), $instance->getConnectionName());

        return 'lyre_admin:metadata:'.sha1($modelClass).':'.$fingerprint;
    }

    private function store(): CacheRepository
    {
        $storeName = config('lyre-filament-admin.cache.metadata_store');

        return $storeName ? Cache::store($storeName) : Cache::store();
    }
}
