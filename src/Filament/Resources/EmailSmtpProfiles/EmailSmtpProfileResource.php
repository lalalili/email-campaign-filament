<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\Pages\CreateEmailSmtpProfile;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\Pages\EditEmailSmtpProfile;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\Pages\ListEmailSmtpProfiles;

class EmailSmtpProfileResource extends Resource
{
    protected static ?string $model = EmailSmtpProfile::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    protected static ?string $navigationLabel = 'SMTP 設定檔';

    protected static ?string $modelLabel = 'SMTP 設定檔';

    protected static ?string $pluralModelLabel = 'SMTP 設定檔';

    public static function getNavigationGroup(): ?string
    {
        return '郵件行銷';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名稱')
                ->required()
                ->maxLength(100),

            Select::make('mailer')
                ->label('驅動')
                ->options([
                    'smtp' => 'SMTP',
                    'log' => 'Log（測試用）',
                ])
                ->required()
                ->default('smtp')
                ->live(),

            TextInput::make('host')
                ->label('Host')
                ->maxLength(255)
                ->visible(fn ($get) => $get('mailer') === 'smtp'),

            TextInput::make('port')
                ->label('Port')
                ->numeric()
                ->default(587)
                ->visible(fn ($get) => $get('mailer') === 'smtp'),

            Select::make('encryption')
                ->label('加密')
                ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => '無'])
                ->nullable()
                ->visible(fn ($get) => $get('mailer') === 'smtp'),

            TextInput::make('username')
                ->label('帳號')
                ->maxLength(255)
                ->visible(fn ($get) => $get('mailer') === 'smtp'),

            TextInput::make('password')
                ->label('密碼')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->visible(fn ($get) => $get('mailer') === 'smtp'),

            TextInput::make('from_address')
                ->label('寄件人 Email')
                ->email()
                ->required()
                ->maxLength(255),

            TextInput::make('from_name')
                ->label('寄件人名稱')
                ->required()
                ->maxLength(100),

            Toggle::make('is_default')
                ->label('設為預設'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名稱')->sortable()->searchable(),
                TextColumn::make('mailer')->label('驅動'),
                TextColumn::make('from_address')->label('寄件人 Email'),
                TextColumn::make('from_name')->label('寄件人名稱'),
                IconColumn::make('is_default')->label('預設')->boolean(),
            ])
            ->actions([
                EditAction::make()->label('編輯'),
                DeleteAction::make()->label('刪除'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailSmtpProfiles::route('/'),
            'create' => CreateEmailSmtpProfile::route('/create'),
            'edit' => EditEmailSmtpProfile::route('/{record}/edit'),
        ];
    }
}
