<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\DeliveriesRelationManager;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\RecipientsRelationManager;

class ViewEmailCampaign extends ViewRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('編輯'),

            \Filament\Actions\Action::make('send')
                ->label('立即寄出')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, [EmailCampaignStatus::Draft, EmailCampaignStatus::Scheduled]))
                ->action(fn () => app(SendCampaignAction::class)->execute($this->record))
                ->requiresConfirmation()
                ->modalHeading('確認立即寄出')
                ->modalDescription('將對所有收件人派發寄送任務，確定嗎？'),

            \Filament\Actions\Action::make('schedule')
                ->label('設定排程')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => $this->record->status === EmailCampaignStatus::Draft)
                ->form([
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_at')
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
