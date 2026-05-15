<?php

namespace Lyre\Filament\Admin\Console;

use Illuminate\Console\Command;
use Lyre\Filament\Admin\Metadata\MetadataCache;

class ShowCommand extends Command
{
    protected $signature = 'lyre-admin:show {model : Fully-qualified model class}';

    protected $description = 'Dump the resolved metadata for a model.';

    public function handle(MetadataCache $cache): int
    {
        $modelClass = (string) $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $metadata = $cache->get($modelClass);

        $this->info("Model: {$metadata->modelClass}");
        $this->line("Table: {$metadata->table}");
        $this->line('Lyre: '.($metadata->isLyreCompatible ? 'yes' : 'no'));
        $this->line('Soft Deletes: '.($metadata->softDeletes ? 'yes' : 'no'));
        $this->line('Display column: '.$metadata->displayColumn);

        $columnRows = [];
        foreach ($metadata->columns as $column) {
            $columnRows[] = [
                $column->name,
                $column->dbType ?? '?',
                $column->inferredType,
                $column->nullable ? 'yes' : 'no',
                $column->isForeign ? ($column->foreignTable.'.'.$column->foreignColumn) : '',
                $column->isSystemManaged ? 'yes' : '',
            ];
        }
        $this->newLine();
        $this->line('Columns');
        $this->table(['Name', 'DB Type', 'Inferred', 'Nullable', 'FK -> ', 'System'], $columnRows);

        $relationRows = [];
        foreach ($metadata->relationships as $relation) {
            $relationRows[] = [
                $relation->name,
                $relation->type,
                $relation->relatedModel ?? '?',
                $relation->foreignKey ?? '',
            ];
        }
        $this->newLine();
        $this->line('Relationships');
        $this->table(['Name', 'Type', 'Related', 'FK'], $relationRows);

        return self::SUCCESS;
    }
}
