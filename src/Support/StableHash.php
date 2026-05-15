<?php

namespace Lyre\Filament\Admin\Support;

final class StableHash
{
    public static function ofModel(string $modelClass): string
    {
        return substr(sha1($modelClass), 0, 12);
    }

    public static function generatedClassName(string $modelClass, string $suffix): string
    {
        return 'Mdl'.self::ofModel($modelClass).$suffix;
    }
}
