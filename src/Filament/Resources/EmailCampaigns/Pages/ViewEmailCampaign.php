<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Lalalili\EmailCampaign\Actions\SendTestCampaignEmailAction;
use Lalalili\EmailCampaign\Actions\SyncAudienceListToCampaignRecipientsAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions\ResetStalledCampaignRecordAction;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions\SendCampaignRecordAction;
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

            Action::make('test_send')
                ->label('測試寄送')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->form([
                    TextInput::make('test_email')
                        ->label('測試收件信箱')
                        ->email()
                        ->required()
                        ->default(fn (): ?string => filled($email = data_get(auth()->user(), 'email')) ? (string) $email : null),
                    Select::make('sample_recipient_id')
                        ->label('套用個人化變數的樣本收件人')
                        ->options(fn () => $this->record->recipients()->limit(50)->pluck('email', 'id'))
                        ->placeholder('（不套用，使用空白變數）')
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $recipient = filled($data['sample_recipient_id'] ?? null)
                        ? $this->record->recipients()->whereKey($data['sample_recipient_id'])->first()
                        : null;

                    try {
                        $rendered = app(SendTestCampaignEmailAction::class)->execute(
                            $this->record,
                            $data['test_email'],
                            $recipient,
                        );

                        $notification = Notification::make()->title('測試信已寄出')->success()
                            ->body("已寄至 {$data['test_email']}。");

                        if ($rendered->missingVariables !== []) {
                            $notification->warning()
                                ->body("已寄至 {$data['test_email']}。注意：以下變數未取得值 — ".implode('、', $rendered->missingVariables));
                        }

                        $notification->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('測試寄送失敗')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalSubmitActionLabel('寄出測試信'),

            SendCampaignRecordAction::make()
                ->after(fn () => $this->record->refresh()),

            ResetStalledCampaignRecordAction::make()
                ->after(fn () => $this->record->refresh()),

            Action::make('schedule')
                ->label('設定排程')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->visible(fn () => $this->record->status === EmailCampaignStatus::Draft)
                ->form([
                    DateTimePicker::make('scheduled_at')
                        ->label('排程寄送時間')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => EmailCampaignStatus::Scheduled,
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

            Section::make('寄送統計')
                ->columns(3)
                ->schema([
                    TextEntry::make('stats_total')->label('收件人總數')
                        ->state(fn (): int => $this->deliveryStats()['total']),
                    TextEntry::make('stats_sent')->label('已寄出')
                        ->state(fn (): int => $this->deliveryStats()['sent']),
                    TextEntry::make('stats_rate')->label('成功率')
                        ->state(fn (): string => $this->deliveryStats()['rate'].'%'),
                    TextEntry::make('stats_failed')->label('失敗')
                        ->state(fn (): int => $this->deliveryStats()['failed']),
                    TextEntry::make('stats_skipped')->label('略過')
                        ->state(fn (): int => $this->deliveryStats()['skipped']),
                    TextEntry::make('stats_pending')->label('待寄送')
                        ->state(fn (): int => $this->deliveryStats()['pending']),
                ]),
        ]);
    }

    /** @var array{total: int, sent: int, failed: int, skipped: int, pending: int, rate: float} */
    private array $deliveryStatsCache;

    /**
     * 單次 grouped 查詢彙整寄送統計，於同一次 infolist 渲染間記憶化。
     *
     * @return array{total: int, sent: int, failed: int, skipped: int, pending: int, rate: float}
     */
    public function deliveryStats(): array
    {
        if (isset($this->deliveryStatsCache)) {
            return $this->deliveryStatsCache;
        }

        $counts = $this->record->deliveries()
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sent = (int) ($counts[EmailDeliveryStatus::Sent->value] ?? 0);
        $failed = (int) ($counts[EmailDeliveryStatus::Failed->value] ?? 0);
        $skipped = (int) ($counts[EmailDeliveryStatus::Skipped->value] ?? 0);
        $pending = (int) ($counts[EmailDeliveryStatus::Pending->value] ?? 0);
        $total = $this->record->recipients()->count();
        $rate = $total > 0 ? round($sent / $total * 100, 1) : 0.0;

        return $this->deliveryStatsCache = compact('total', 'sent', 'failed', 'skipped', 'pending', 'rate');
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
