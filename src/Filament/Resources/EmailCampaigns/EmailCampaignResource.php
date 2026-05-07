<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Lalalili\EmailCampaign\Actions\ResetStalledCampaignAction;
use Lalalili\EmailCampaign\Actions\SendCampaignAction;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\CreateEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\EditEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ListEmailCampaigns;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ViewEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\DeliveriesRelationManager;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\RecipientsRelationManager;

class EmailCampaignResource extends Resource
{
    protected static ?string $model = EmailCampaign::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-envelope';
    }

    protected static ?string $navigationLabel = 'EDM 活動';

    protected static ?string $modelLabel = '活動';

    protected static ?string $pluralModelLabel = 'EDM 活動';

    public static function getNavigationGroup(): ?string
    {
        return '郵件行銷';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('活動名稱')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('說明')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('smtp_profile_id')
                ->label('SMTP 設定檔')
                ->options(EmailSmtpProfile::pluck('name', 'id'))
                ->placeholder('（使用系統預設）')
                ->nullable(),

            Select::make('survey_id')
                ->label('問卷')
                ->options(fn (): array => self::surveyOptions())
                ->placeholder('（不帶入問卷連結）')
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $set('audience_list_id', self::surveyAudienceListId($state));
                    $set('audience_email_column', null);
                })
                ->helperText('選擇問卷後，主旨與 EDM 內容可使用 {{ survey_url }} 帶入每位收件人的問卷個性化網址。')
                ->columnSpanFull(),

            Select::make('audience_list_id')
                ->label('活動名單')
                ->options(fn (Get $get): array => self::audienceListOptions($get('survey_id')))
                ->placeholder('（手動管理收件人）')
                ->searchable()
                ->nullable()
                ->live()
                ->disabled(fn (Get $get): bool => filled($get('survey_id')))
                ->dehydrated()
                ->rules([
                    fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $surveyAudienceListId = self::surveyAudienceListId($get('survey_id'));

                        if ($surveyAudienceListId !== null && (string) $value !== (string) $surveyAudienceListId) {
                            $fail('活動名單必須與問卷設定的個性化名單一致。');
                        }
                    },
                ])
                ->helperText('選擇名單後，排程時會固定收件人快照。若已選問卷，活動名單會自動鎖定為問卷的個性化名單。'),

            Select::make('audience_email_column')
                ->label('Email 欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('audience_list_id')))
                ->searchable()
                ->nullable()
                ->required(fn ($get): bool => filled($get('audience_list_id')))
                ->helperText('系統會依此欄位取得每筆名單資料的收件 Email。'),

            Select::make('status')
                ->label('狀態')
                ->options(collect(EmailCampaignStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->required()
                ->default(EmailCampaignStatus::Draft->value),

            DateTimePicker::make('scheduled_at')
                ->label('排程寄送時間')
                ->nullable()
                ->helperText('留空 = 立即寄送（按「寄出」後觸發）'),

            TextInput::make('subject_template')
                ->label('主旨模板')
                ->required()
                ->maxLength(500)
                ->helperText('支援 {{ survey_url }}、{{ name }}、{{ user_name }}、{{ campaign_name }}、{{ email }} 及名單欄位變數。')
                ->columnSpanFull(),

            RichEditor::make('html_template')
                ->label('EDM 內容')
                ->nullable()
                ->helperText('可使用個性化變數，例如：親愛的 {{ name }} 會員，請填寫 {{ survey_url }}。寄送 HTML 時，{{ survey_url }} 會自動轉為另開分頁連結。')
                ->columnSpanFull(),

            Repeater::make('extras_json')
                ->label('個性化對應')
                ->schema([
                    Select::make('source')
                        ->label('名單欄位')
                        ->options(fn (Get $get): array => self::audienceColumnOptions($get('../../audience_list_id')))
                        ->searchable()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                    TextInput::make('keyword')
                        ->label('個性化關鍵字')
                        ->placeholder('例如：number')
                        ->required()
                        ->regex('/^[A-Za-z_][A-Za-z0-9_.]*$/')
                        ->notIn(['survey_url', 'survey_title', 'survey_public_key'])
                        ->helperText('只能使用英文字母、數字、底線或點，且不可用數字開頭。'),
                ])
                ->columns(2)
                ->addActionLabel('新增個性化對應')
                ->helperText('左邊選擇名單欄位，右邊設定 EDM 內容與主旨可使用的關鍵字，例如：車牌號碼 -> number 後可使用 {{ number }}。')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('活動名稱')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        EmailCampaignStatus::Sent => 'success',
                        EmailCampaignStatus::Sending => 'warning',
                        EmailCampaignStatus::Scheduled => 'info',
                        EmailCampaignStatus::Failed => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof EmailCampaignStatus ? $state->label() : $state),

                TextColumn::make('recipients_count')
                    ->counts('recipients')
                    ->label('收件人數'),

                TextColumn::make('audience_skipped_count')
                    ->label('略過筆數')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deliveries_count')
                    ->counts('deliveries')
                    ->label('寄送數'),

                TextColumn::make('scheduled_at')
                    ->label('排程時間')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('sent_at')
                    ->label('寄出時間')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options(collect(EmailCampaignStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()->label('檢視'),
                    EditAction::make()->label('編輯'),

                    Action::make('send')
                        ->label(fn (EmailCampaign $record): string => $record->status === EmailCampaignStatus::Failed ? '重新寄送' : '立即寄出')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (EmailCampaign $record) => in_array($record->status, [EmailCampaignStatus::Draft, EmailCampaignStatus::Scheduled, EmailCampaignStatus::Failed]))
                        ->action(function (EmailCampaign $record): void {
                            try {
                                app(SendCampaignAction::class)->execute($record);

                                Notification::make()
                                    ->title('已派發寄送任務')
                                    ->success()
                                    ->send();
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
                        ->modalDescription('將對所有收件人派發寄送任務，確定嗎？'),

                    Action::make('reset_stalled')
                        ->label('重設為草稿')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
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
                        ->modalDescription('僅適用於尚未產生任何寄送紀錄的寄送中活動。'),

                    DeleteAction::make()->label('刪除'),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailCampaigns::route('/'),
            'create' => CreateEmailCampaign::route('/create'),
            'edit' => EditEmailCampaign::route('/{record}/edit'),
            'view' => ViewEmailCampaign::route('/{record}'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function audienceListOptions(mixed $surveyId = null): array
    {
        if (! DB::getSchemaBuilder()->hasTable('audience_lists')) {
            return [];
        }

        $surveyAudienceListId = self::surveyAudienceListId($surveyId);

        if ($surveyAudienceListId !== null) {
            return DB::table('audience_lists')
                ->where('id', $surveyAudienceListId)
                ->pluck('name', 'id')
                ->toArray();
        }

        return DB::table('audience_lists')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    private static function audienceColumnOptions(mixed $audienceListId): array
    {
        if (! $audienceListId || ! DB::getSchemaBuilder()->hasTable('audience_lists')) {
            return [];
        }

        $columns = DB::table('audience_lists')
            ->where('id', $audienceListId)
            ->value('columns_json');

        $decoded = is_string($columns) ? json_decode($columns, true) : $columns;

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->mapWithKeys(fn (string $column): array => [$column => $column])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function surveyOptions(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('surveys')) {
            return [];
        }

        return DB::table('surveys')
            ->where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title', 'settings_json'])
            ->filter(fn (object $survey): bool => self::settingsAudienceListId($survey->settings_json ?? null) !== null)
            ->mapWithKeys(fn (object $survey): array => [$survey->id => $survey->title])
            ->all();
    }

    private static function surveyAudienceListId(mixed $surveyId): ?int
    {
        if (! $surveyId || ! DB::getSchemaBuilder()->hasTable('surveys')) {
            return null;
        }

        $settings = DB::table('surveys')
            ->where('id', $surveyId)
            ->value('settings_json');

        return self::settingsAudienceListId($settings);
    }

    private static function settingsAudienceListId(mixed $settings): ?int
    {
        $decoded = is_string($settings) ? json_decode($settings, true) : $settings;

        if (! is_array($decoded)) {
            return null;
        }

        $audienceListId = data_get($decoded, 'personalization.audience_list_id');

        return filled($audienceListId) ? (int) $audienceListId : null;
    }
}
