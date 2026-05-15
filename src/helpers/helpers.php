<?php

if (! function_exists('lyre_admin_runtime_path')) {
    function lyre_admin_runtime_path(string $append = ''): string
    {
        $base = config('lyre-filament-admin.runtime.path')
            ?: storage_path('framework/lyre-admin');

        return $append === '' ? $base : rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($append, DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('lyre_admin_runtime_namespace')) {
    function lyre_admin_runtime_namespace(): string
    {
        return trim(
            config('lyre-filament-admin.runtime.namespace', 'Lyre\\Filament\\Admin\\Runtime\\Generated'),
            '\\'
        );
    }
}
