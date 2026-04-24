<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

class ListEmailCampaigns extends ListRecords
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('新增活動')];
    }
}
