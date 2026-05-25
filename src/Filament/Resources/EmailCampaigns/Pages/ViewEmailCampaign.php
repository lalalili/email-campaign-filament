<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Lalalili\EmailCampaign\Actions\ResetStalledCampaignAction;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\DeliveriesRelationManager;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\RecipientsRelationManager;

/**
 * @property EmailCampaign $record
 */
class ViewEmailCampaign extends ViewRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('編輯'),

            Action::make('send')
                ->label(fn () => $this->record->status === EmailCampaignStatus::Failed ? '重新寄送' : '立即寄出')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, [EmailCampaignStatus::Draft, EmailCampaignStatus::Scheduled, EmailCampaignStatus::Failed]))
                ->action(function (): void {
                    try {
                        app(SendCampaignAction::class)->execute($this->record);
                        $this->record->refresh();

                        Notification::make()
                            ->title('已派發寄送任務')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        $this->record->refresh();

                        Notification::make()
                            ->title('寄送任務派發失敗')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('確認立即寄出')
                ->modalDescription('將對所有收件人派發寄送任務，確定嗎？'),

            Action::make('reset_stalled')
                ->label('重設為草稿')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === EmailCampaignStatus::Sending && ! $this->record->deliveries()->exists())
                ->action(function (): void {
                    if (app(ResetStalledCampaignAction::class)->execute($this->record)) {
                        $this->record->refresh();

                        Notification::make()
                            ->title('已重設為草稿')
                            ->success()
                            ->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title('無法重設活動')
                        ->body('此活動已有寄送紀錄或狀態已變更。')
                        ->danger()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('確認重設為草稿')
                ->modalDescription('僅適用於尚未產生任何寄送紀錄的寄送中活動。'),

            Action::make('schedule')
                ->label('設定排程')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => $this->record->status === EmailCampaignStatus::Draft)
                ->form([
                    DateTimePicker::make('scheduled_at')
                        ->label('排程寄送時間')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'       => EmailCampaignStatus::Scheduled,
                        'scheduled_at' => $data['scheduled_at'],
                    ]);

                    app(SyncAudienceListToCampaignRecipientsAction::class)->execute($this->record);
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            TextEntry::make('name')->label('活動名稱'),
            TextEntry::make('status')
                ->label('狀態')
                ->formatStateUsing(fn ($state) => $state instanceof EmailCampaignStatus ? $state->label() : $state),
            TextEntry::make('subject_template')->label('主旨模板'),
            TextEntry::make('audience_snapshot_at')->label('名單快照時間')->dateTime()->placeholder('—'),
            TextEntry::make('audience_skipped_count')->label('名單略過筆數')->placeholder('0'),
            TextEntry::make('scheduled_at')->label('排程時間')->dateTime()->placeholder('—'),
            TextEntry::make('sent_at')->label('寄出時間')->dateTime()->placeholder('—'),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            RecipientsRelationManager::class,
            DeliveriesRelationManager::class,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? '活動詳情';
    }
}
