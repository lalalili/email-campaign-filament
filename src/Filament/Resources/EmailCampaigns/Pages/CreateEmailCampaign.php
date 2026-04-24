<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

class CreateEmailCampaign extends CreateRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('活動已建立')
            ->body('請在下方匯入收件人後即可寄送。');
    }
}
