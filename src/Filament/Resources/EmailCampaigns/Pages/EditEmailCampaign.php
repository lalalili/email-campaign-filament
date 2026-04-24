<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

class EditEmailCampaign extends EditRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('刪除')];
    }
}
