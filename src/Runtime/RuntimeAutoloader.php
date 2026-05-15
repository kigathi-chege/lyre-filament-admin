<?php

namespace Lyre\Filament\Admin\Runtime;

class RuntimeAutoloader
{
    private bool $registered = false;

    public function __construct(
        private readonly RuntimeClassRegistry $registry,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        spl_autoload_register([$this, 'load'], throw: true, prepend: true);
        $this->registered = true;
    }

    public function load(string $class): void
    {
        $file = $this->registry->fileFor($class);

        if ($file === null) {
            return;
        }

        if (! is_file($file)) {
            return;
        }

        require_once $file;
    }
}
