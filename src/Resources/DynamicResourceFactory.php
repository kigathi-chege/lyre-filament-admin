<?php

namespace Lyre\Filament\Admin\Resources;

use Lyre\Filament\Admin\Discovery\ModelDescriptor;
use Lyre\Filament\Admin\Runtime\RuntimeClassFactory;

class DynamicResourceFactory
{
    public function __construct(
        private readonly RuntimeClassFactory $runtime,
        private readonly DynamicResourceRegistry $registry,
    ) {}

    public function produce(ModelDescriptor $descriptor): string
    {
        $family = $this->runtime->generateResourceFamily(
            $descriptor->modelClass,
            $descriptor->slug,
        );

        $this->registry->bind($descriptor->modelClass, $family['resource']);

        return $family['resource'];
    }
}
