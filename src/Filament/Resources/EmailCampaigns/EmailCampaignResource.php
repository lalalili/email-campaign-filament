<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaign\Support\VariableProviderRegistry;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions\ResetStalledCampaignRecordAction;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Actions\SendCampaignRecordAction;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\CreateEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\EditEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ListEmailCampaigns;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ViewEmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\DeliveriesRelationManager;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\RecipientsRelationManager;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Support\ImageUploadSanitizer;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EmailCampaignResource extends Resource
{
    protected static ?string $model = EmailCampaign::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-envelope';
    }

    protected static ?string $navigationLabel = 'Email 活動';

    protected static ?string $modelLabel = 'Email 活動';

    protected static ?string $pluralModelLabel = 'Email 活動';

    public static function getNavigationGroup(): ?string
    {
        return '活動自動化';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                    $set('audience_list_id', self::surveyAudienceListId($state));
                    $set('audience_email_column', self::surveyAudienceEmailColumn($state));
                    $set('extras_json', self::surveyPersonalizationMappings($state, $get('extras_json')));
                })
                ->helperText('選擇問卷後，主旨與 Email 內容可使用 {{ survey_url }} 帶入每位收件人的問卷個性化網址。')
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
                ->label('Email 內容')
                ->nullable()
                ->helperText('可使用個性化變數，例如：親愛的 {{ name }} 會員，請填寫 {{ survey_url }}。寄送 HTML 時，{{ survey_url }} 會自動轉為另開分頁連結。')
                ->fileAttachmentsDisk((string) config('marketing.filament.email_images.disk', 'public'))
                ->fileAttachmentsDirectory((string) config('marketing.filament.email_images.directory', 'marketing-emails'))
                ->fileAttachmentsVisibility('public')
                ->fileAttachmentsAcceptedFileTypes((array) config('marketing.filament.email_images.accepted_file_types', [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                ]))
                ->fileAttachmentsMaxSize((int) config('marketing.filament.email_images.max_size', 5120))
                ->saveUploadedFileAttachmentUsing(
                    fn (TemporaryUploadedFile $file): ?string => app(ImageUploadSanitizer::class)->store(
                        $file,
                        (string) config('marketing.filament.email_images.directory', 'marketing-emails'),
                        (string) config('marketing.filament.email_images.disk', 'public'),
                        'public',
                    ) ?: null,
                )
                ->preventFileAttachmentPathTampering(
                    allowFilePathUsing: fn (string $file): bool => str_starts_with($file, (string) config('marketing.filament.email_images.directory', 'marketing-emails').'/'),
                )
                ->columnSpanFull(),

            Placeholder::make('available_variables')
                ->label('可用個性化變數')
                ->content(fn (Get $get): HtmlString => self::availableVariablesContent($get('audience_list_id')))
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
                ->helperText('左邊選擇名單欄位，右邊設定 Email 內容與主旨可使用的關鍵字，例如：車牌號碼 -> number 後可使用 {{ number }}。')
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
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()->label('檢視'),
                    EditAction::make()->label('編輯'),

                    SendCampaignRecordAction::make(),

                    ResetStalledCampaignRecordAction::make(),

                    self::deleteAction(),
                    self::forceDeleteAction(),
                    self::restoreAction(),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('刪除')
            ->modalHeading(fn (EmailCampaign $record): string => "刪除 {$record->name}")
            ->modalDescription('刪除後可從「已刪除」還原，收件人、寄送與事件紀錄都會保留，確定要進行嗎?')
            ->before(function (DeleteAction $action, EmailCampaign $record): void {
                if ($record->canBeDeleted()) {
                    return;
                }

                Notification::make()
                    ->title('無法刪除 Email 活動')
                    ->body($record->deletionBlockReason())
                    ->danger()
                    ->send();

                $action->halt();
            });
    }

    public static function forceDeleteAction(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->label('永久刪除')
            ->modalHeading(fn (EmailCampaign $record): string => "永久刪除 {$record->name}")
            ->modalDescription('永久刪除後將無法復原，且會一併刪除收件人、寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->label('還原')
            ->modalHeading(fn (EmailCampaign $record): string => "還原 {$record->name}")
            ->modalDescription('還原後，Email 活動會重新顯示於活動清單。');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
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
        if (! $audienceListId) {
            return [];
        }

        return AudienceList::query()->find((int) $audienceListId)?->columnOptions() ?? [];
    }

    /**
     * 列出目前可用的個性化變數：VariableProviderRegistry 描述的固定變數，
     * 加上所選名單的欄位。
     *
     * 名單欄位會被 RecipientVariableProvider 直接攤平成變數，作者可原樣使用，
     * 但那是執行期才成立的行為，過去在後台看不到——只能猜欄位名，猜錯就會把
     * 字面的 {{ 變數 }} 寄給真實客戶。
     */
    private static function availableVariablesContent(mixed $audienceListId): HtmlString
    {
        $variables = app(VariableProviderRegistry::class)->describe();
        $reserved = array_column($variables, 'key');

        $sections = [];

        if ($variables !== []) {
            $sections[] = '<p class="mt-2 font-medium">系統變數</p><ul class="mt-1 space-y-1">'
                .self::variableListItems($variables).'</ul>';
        }

        $audienceColumns = self::audienceVariableDescriptors($audienceListId, $reserved);

        if ($audienceColumns !== []) {
            $sections[] = '<p class="mt-3 font-medium">名單欄位</p><ul class="mt-1 space-y-1">'
                .self::variableListItems($audienceColumns).'</ul>';
        } elseif (filled($audienceListId)) {
            $sections[] = '<p class="mt-3 text-gray-500">此名單沒有可用的欄位變數。</p>';
        } else {
            $sections[] = '<p class="mt-3 text-gray-500">選擇名單後，這裡會列出該名單的欄位變數。</p>';
        }

        return new HtmlString(
            '<div class="text-sm text-gray-600 dark:text-gray-300">在主旨與內容中可插入下列變數：'
            .implode('', $sections)
            .'<p class="mt-3 text-gray-500">若要改用其他名稱，可於下方「個性化對應」另行設定關鍵字。</p></div>',
        );
    }

    /**
     * @param  list<array{key: string, label: string}>  $variables
     */
    private static function variableListItems(array $variables): string
    {
        return collect($variables)
            ->map(fn (array $variable): string => sprintf(
                '<li><code>{{ %s }}</code> — %s</li>',
                e($variable['key']),
                e($variable['label']),
            ))
            ->implode('');
    }

    /**
     * 名單欄位轉成變數描述。與系統變數同名者不列出：RecipientVariableProvider
     * 保留那些鍵，名單同名欄位不會覆蓋它們，列出來反而誤導。
     *
     * @param  list<string>  $reserved
     * @return list<array{key: string, label: string}>
     */
    private static function audienceVariableDescriptors(mixed $audienceListId, array $reserved): array
    {
        $descriptors = [];

        foreach (self::audienceColumnOptions($audienceListId) as $key => $label) {
            $key = (string) $key;

            if ($key === '' || in_array($key, $reserved, true)) {
                continue;
            }

            $descriptors[] = [
                'key' => $key,
                'label' => (string) $label,
            ];
        }

        return $descriptors;
    }

    /**
     * @return array<int, string>
     */
    private static function surveyOptions(): array
    {
        if (! class_exists(Survey::class)) {
            return [];
        }

        return Survey::query()
            ->where('status', SurveyStatus::Published->value)
            ->orderBy('title')
            ->get()
            ->filter(fn (Survey $survey): bool => $survey->settings()->audienceListId !== null)
            ->mapWithKeys(fn (Survey $survey): array => [$survey->id => $survey->title])
            ->all();
    }

    private static function surveyAudienceListId(mixed $surveyId): ?int
    {
        if (! $surveyId || ! class_exists(Survey::class)) {
            return null;
        }

        $survey = Survey::query()->find((int) $surveyId);

        return $survey?->settings()->audienceListId;
    }

    private static function surveyAudienceEmailColumn(mixed $surveyId): ?string
    {
        if (! $surveyId || ! class_exists(Survey::class)) {
            return null;
        }

        $survey = Survey::query()->find((int) $surveyId);
        $emailColumn = $survey?->settings()->emailColumn;

        return filled($emailColumn) ? $emailColumn : null;
    }

    /**
     * @return array<int, array{source: string, keyword: string}>|null
     */
    private static function surveyPersonalizationMappings(mixed $surveyId, mixed $currentMappings): ?array
    {
        $mappings = collect(is_array($currentMappings) ? $currentMappings : [])
            ->filter(fn (mixed $mapping): bool => is_array($mapping))
            ->map(fn (array $mapping): array => [
                'source' => trim((string) ($mapping['source'] ?? '')),
                'keyword' => trim((string) ($mapping['keyword'] ?? '')),
            ])
            ->filter(fn (array $mapping): bool => $mapping['source'] !== '' && $mapping['keyword'] !== '' && $mapping['keyword'] !== 'user_name')
            ->values()
            ->all();

        $nameColumn = self::surveyAudienceNameColumn($surveyId);

        if (filled($nameColumn)) {
            $mappings[] = [
                'source' => $nameColumn,
                'keyword' => 'user_name',
            ];
        }

        return $mappings === [] ? null : $mappings;
    }

    private static function surveyAudienceNameColumn(mixed $surveyId): ?string
    {
        if (! $surveyId || ! class_exists(Survey::class)) {
            return null;
        }

        $survey = Survey::query()->find((int) $surveyId);
        $nameColumn = $survey?->settings()->nameColumn;

        return filled($nameColumn) ? $nameColumn : null;
    }
}
