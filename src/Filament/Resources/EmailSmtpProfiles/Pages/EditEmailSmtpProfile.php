<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\EmailSmtpProfileResource;

class EditEmailSmtpProfile extends EditRecord
{
    protected static string $resource = EmailSmtpProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('刪除')];
    }
}
