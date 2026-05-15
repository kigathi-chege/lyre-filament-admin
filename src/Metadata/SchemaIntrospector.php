<?php

namespace Lyre\Filament\Admin\Metadata;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

final class SchemaIntrospector
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $columnCache = [];

    /**
     * @var array<string, array<string, array{table: string, column: string}>>
     */
    private array $foreignCache = [];

    public function fingerprint(string $table, ?string $connection = null): string
    {
        $columns = $this->columns($table, $connection);

        $payload = [];
        foreach ($columns as $name => $info) {
            $payload[$name] = $info['type'] ?? null;
        }

        return sha1(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function columns(string $table, ?string $connection = null): array
    {
        $key = ($connection ?? 'default').':'.$table;

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        $builder = $this->builder($connection);

        if (! $builder->hasTable($table)) {
            return $this->columnCache[$key] = [];
        }

        $columns = [];

        try {
            foreach ($builder->getColumns($table) as $col) {
                $columns[$col['name']] = $col;
            }
        } catch (\Throwable $e) {
            foreach ($builder->getColumnListing($table) as $name) {
                $columns[$name] = [
                    'name' => $name,
                    'type' => $builder->getColumnType($table, $name),
                    'nullable' => true,
                    'default' => null,
                ];
            }
        }

        return $this->columnCache[$key] = $columns;
    }

    /**
     * @return array<string, array{table: string, column: string}>
     */
    public function foreignKeys(string $table, ?string $connection = null): array
    {
        $key = ($connection ?? 'default').':'.$table;

        if (array_key_exists($key, $this->foreignCache)) {
            return $this->foreignCache[$key];
        }

        $builder = $this->builder($connection);

        $map = [];

        if (! method_exists($builder, 'getForeignKeys')) {
            return $this->foreignCache[$key] = [];
        }

        try {
            foreach ($builder->getForeignKeys($table) as $fk) {
                $columns = $fk['columns'] ?? [];
                $foreignColumns = $fk['foreign_columns'] ?? [];
                $foreignTable = $fk['foreign_table'] ?? null;

                if (! $foreignTable) {
                    continue;
                }

                foreach ($columns as $idx => $col) {
                    $map[$col] = [
                        'table' => $foreignTable,
                        'column' => $foreignColumns[$idx] ?? 'id',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Best-effort; FK discovery is optional.
        }

        return $this->foreignCache[$key] = $map;
    }

    public function flush(): void
    {
        $this->columnCache = [];
        $this->foreignCache = [];
    }

    private function builder(?string $connection): Builder
    {
        return $connection
            ? Schema::connection($connection)
            : Schema::getFacadeRoot();
    }
}
