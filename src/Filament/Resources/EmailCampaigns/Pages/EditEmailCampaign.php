<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

/**
 * @property EmailCampaign $record
 */
class EditEmailCampaign extends EditRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('刪除')];
    }

    protected function afterSave(): void
    {
        if ($this->record->status === EmailCampaignStatus::Scheduled) {
            app(SyncAudienceListToCampaignRecipientsAction::class)->execute($this->record);
        }
    }
}
