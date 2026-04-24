<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\EmailSmtpProfileResource;

class ListEmailSmtpProfiles extends ListRecords
{
    protected static string $resource = EmailSmtpProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('新增設定檔')];
    }
}
