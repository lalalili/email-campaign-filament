<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * 「立即寄出／重新寄送」動作，供列表 row action 與檢視頁 header action 共用。
 * Filament 會依情境（table row / record page）注入 $record。
 */
class SendCampaignRecordAction
{
    public static function make(): Action
    {
        return Action::make('send')
            ->label(fn (EmailCampaign $record): string => $record->status === EmailCampaignStatus::Failed ? '重新寄送' : '立即寄出')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->authorize('update')
            ->visible(fn (EmailCampaign $record): bool => in_array($record->status, [EmailCampaignStatus::Draft, EmailCampaignStatus::Scheduled, EmailCampaignStatus::Failed], true))
            ->action(function (EmailCampaign $record): void {
                try {
                    if (app(SendCampaignAction::class)->execute($record)) {
                        Notification::make()
                            ->title('已派發寄送任務')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('活動已在寄送中或已寄出')
                            ->body('未重複派發寄送任務。')
                            ->warning()
                            ->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('寄送任務派發失敗')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalHeading('確認立即寄出')
            ->modalDescription('將對所有收件人派發寄送任務，確定嗎？');
    }
}
