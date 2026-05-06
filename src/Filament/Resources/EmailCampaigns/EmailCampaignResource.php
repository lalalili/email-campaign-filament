<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
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

            Select::make('audience_list_id')
                ->label('活動名單')
                ->options(fn (): array => self::audienceListOptions())
                ->placeholder('（手動管理收件人）')
                ->searchable()
                ->nullable()
                ->live()
                ->helperText('選擇名單後，排程時會固定收件人快照。'),

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
                ->helperText('支援 {{ name }}、{{ user_name }}、{{ campaign_name }}、{{ email }} 及名單欄位變數。')
                ->columnSpanFull(),

            RichEditor::make('html_template')
                ->label('EDM 內容')
                ->nullable()
                ->helperText('可使用個性化變數，例如：親愛的 {{ name }} 會員。')
                ->columnSpanFull(),

            Textarea::make('text_template')
                ->label('純文字內容模板')
                ->rows(5)
                ->nullable()
                ->columnSpanFull(),

            KeyValue::make('extras_json')
                ->label('額外靜態變數')
                ->helperText('例如：coupon_code = SAVE20。這些值可在模板中以 {{ coupon_code }} 取用，且會覆蓋其他來源。')
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
                        EmailCampaignStatus::Sent      => 'success',
                        EmailCampaignStatus::Sending   => 'warning',
                        EmailCampaignStatus::Scheduled => 'info',
                        EmailCampaignStatus::Failed    => 'danger',
                        default                        => 'gray',
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

                    \Filament\Actions\Action::make('send')
                        ->label('立即寄出')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (EmailCampaign $record) => in_array($record->status, [EmailCampaignStatus::Draft, EmailCampaignStatus::Scheduled]))
                        ->action(fn (EmailCampaign $record) => app(SendCampaignAction::class)->execute($record))
                        ->requiresConfirmation()
                        ->modalHeading('確認立即寄出')
                        ->modalDescription('將對所有收件人派發寄送任務，確定嗎？'),

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
            'index'  => ListEmailCampaigns::route('/'),
            'create' => CreateEmailCampaign::route('/create'),
            'edit'   => EditEmailCampaign::route('/{record}/edit'),
            'view'   => ViewEmailCampaign::route('/{record}'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function audienceListOptions(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('audience_lists')) {
            return [];
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
}
