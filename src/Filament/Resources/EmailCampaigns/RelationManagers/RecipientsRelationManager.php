<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = '收件人';

    protected static ?string $modelLabel = '收件人';

    /**
     * external_id 是對應來源系統的識別碼，不是給人閱讀的編號；光看欄位名看不出
     * 用途，列表與表單都掛這段說明。
     *
     * 註：問卷個人化連結的主要比對鍵是 audience_list_row_id（見 SurveyVariableProvider），
     * external_id 只在收件人沒有名單列來源（例如手動匯入）時作為備援。
     */
    public const string EXTERNAL_ID_HINT = '對應來源系統的識別碼，供對帳、去重與跨系統追蹤。名單同步時自動填入名單資料列 ID；手動匯入的收件人另以此欄比對問卷收件人。';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255),

            TextInput::make('user_name')
                ->label('姓名')
                ->maxLength(255),

            TextInput::make('external_id')
                ->label('外部識別碼')
                ->maxLength(255)
                ->helperText(self::EXTERNAL_ID_HINT),

            KeyValue::make('payload_json')
                ->label('自訂欄位')
                ->helperText('例：coupon_code = SAVE20。可在模板中以 {{ coupon_code }} 取用。')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('user_name')->label('姓名')->searchable()->placeholder('—'),
                TextColumn::make('external_id')
                    ->label('外部識別碼')
                    ->extraHeaderAttributes(['title' => self::EXTERNAL_ID_HINT])
                    ->tooltip(self::EXTERNAL_ID_HINT)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('delivery.status')
                    ->label('寄送狀態')
                    ->placeholder('未排入')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '—'),
            ])
            ->headerActions(array_values(array_filter([
                $this->recipientImportAction(),
                CreateAction::make()->label('新增收件人'),
            ])))
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('編輯'),
                    self::deleteAction(),
                ]),
            ])
            ->bulkActions([self::deleteBulkAction()]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('刪除')
            ->modalHeading(fn (EmailCampaignRecipient $record): string => "刪除 {$record->email}")
            ->modalDescription('刪除後將無法復原，且會一併刪除對應的寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
    }

    public static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('批次刪除')
            ->modalHeading(fn (DeleteBulkAction $action): string => '刪除已選取的 '.$action->getSelectedRecords()->count().' 筆收件人')
            ->modalDescription('刪除後將無法復原，且會一併刪除對應的寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
    }

    /**
     * Excel/CSV bulk import, available when the optional eightynine/filament-excel-import
     * package is installed. Delegates row handling to App\Filament\Imports\EmailCampaignRecipientImport.
     */
    private function recipientImportAction(): ?Action
    {
        $actionClass = 'EightyNine\\ExcelImport\\Tables\\ExcelImportRelationshipAction';

        if (! class_exists($actionClass)) {
            return null;
        }

        $action = $actionClass::make()
            ->label('匯入收件人 (Excel / CSV)')
            ->color('gray');

        $action->{'use'}('App\\Filament\\Imports\\EmailCampaignRecipientImport');

        return $action;
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
