<?php

namespace Lyre\Filament\Admin\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

abstract class DynamicEditRecordPage extends EditRecord
{
    protected static string $resource;

    protected function getHeaderActions(): array
    {
        $actions = [ViewAction::make(), DeleteAction::make()];

        if (method_exists($this->getRecord(), 'trashed')) {
            $actions[] = RestoreAction::make();
            $actions[] = ForceDeleteAction::make();
        }

        return $actions;
    }
}
