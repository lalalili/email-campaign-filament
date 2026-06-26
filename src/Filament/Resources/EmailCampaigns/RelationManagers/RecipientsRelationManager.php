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

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = '收件人';

    protected static ?string $modelLabel = '收件人';

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
                ->label('外部 ID')
                ->maxLength(255)
                ->helperText('對應系統的使用者/收件人 ID，可用於 survey 整合。'),

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
                TextColumn::make('user_name')->label('姓名')->placeholder('—'),
                TextColumn::make('external_id')->label('外部 ID')->placeholder('—')->toggleable(),
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
                    DeleteAction::make()->label('刪除'),
                ]),
            ])
            ->bulkActions([DeleteBulkAction::make()->label('批次刪除')]);
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
