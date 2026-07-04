<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lalalili\EmailCampaign\Actions\ResetStalledCampaignAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;

/**
 * 「重設為草稿」動作，供列表 row action 與檢視頁 header action 共用。
 */
class ResetStalledCampaignRecordAction
{
    public static function make(): Action
    {
        return Action::make('reset_stalled')
            ->label('重設為草稿')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->authorize('update')
            ->visible(fn (EmailCampaign $record): bool => $record->status === EmailCampaignStatus::Sending && ! $record->deliveries()->exists())
            ->action(function (EmailCampaign $record): void {
                if (app(ResetStalledCampaignAction::class)->execute($record)) {
                    Notification::make()
                        ->title('已重設為草稿')
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('無法重設活動')
                    ->body('此活動已有寄送紀錄或狀態已變更。')
                    ->danger()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading('確認重設為草稿')
            ->modalDescription('僅適用於尚未產生任何寄送紀錄的寄送中活動。');
    }
}
