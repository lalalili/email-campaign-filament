<?php

namespace Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = '寄送紀錄';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient.email')->label('Email')->searchable(),
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        EmailDeliveryStatus::Sent    => 'success',
                        EmailDeliveryStatus::Failed  => 'danger',
                        EmailDeliveryStatus::Pending => 'warning',
                        default                      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof EmailDeliveryStatus ? $state->label() : $state),
                TextColumn::make('rendered_subject')->label('實際主旨')->placeholder('—')->toggleable(),
                TextColumn::make('error_message')->label('錯誤訊息')->placeholder('—')->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sent_at')->label('寄出時間')->dateTime()->placeholder('—')->sortable(),
            ])
            ->defaultSort('sent_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
