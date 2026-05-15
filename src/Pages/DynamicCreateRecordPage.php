<?php

namespace Lyre\Filament\Admin\Pages;

use Filament\Resources\Pages\CreateRecord;

abstract class DynamicCreateRecordPage extends CreateRecord
{
    protected static string $resource;
}
