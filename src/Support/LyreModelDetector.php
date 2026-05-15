<?php

namespace Lyre\Filament\Admin\Support;

use Lyre\Model;
use Lyre\Traits\BaseModelTrait;

final class LyreModelDetector
{
    public static function isLyre(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        if (class_exists(Model::class) && is_subclass_of($modelClass, Model::class)) {
            return true;
        }

        if (! trait_exists(BaseModelTrait::class)) {
            return false;
        }

        return in_array(
            BaseModelTrait::class,
            class_uses_recursive($modelClass),
            true
        );
    }
}
