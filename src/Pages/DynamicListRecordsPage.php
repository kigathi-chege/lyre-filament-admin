<?php

namespace Lyre\Filament\Admin\Pages;

use Filament\Resources\Pages\ListRecords;

abstract class DynamicListRecordsPage extends ListRecords
{
    protected static string $resource;
}
