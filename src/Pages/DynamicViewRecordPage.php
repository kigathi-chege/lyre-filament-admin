<?php

namespace Lyre\Filament\Admin\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class DynamicViewRecordPage extends ViewRecord
{
    protected static string $resource;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
