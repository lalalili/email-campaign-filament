<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

/**
 * @property EmailCampaign $record
 */
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
            ->body('可在下方檢視收件人快照與寄送紀錄。');
    }

    protected function afterCreate(): void
    {
        if ($this->record->status === EmailCampaignStatus::Scheduled) {
            app(SyncAudienceListToCampaignRecipientsAction::class)->execute($this->record);
        }
    }
}
