<?php

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailSmtpProfile;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\RelationManagers\RecipientsRelationManager;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\EmailSmtpProfileResource;
use Livewire\Component;

it('describes the permanent impact of deleting an email campaign', function (): void {
    $action = EmailCampaignResource::deleteAction()
        ->record(new EmailCampaign(['name' => '七月電子報']));

    expect($action->getModalHeading())
        ->toBe('刪除 七月電子報')
        ->and($action->getModalDescription())
        ->toBe('刪除後將無法復原，且會一併刪除收件人、寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
});

it('describes the relationship impact of deleting an SMTP profile', function (): void {
    $action = EmailSmtpProfileResource::deleteAction()
        ->record(new EmailSmtpProfile(['name' => '主要 SMTP']));

    expect($action->getModalHeading())
        ->toBe('刪除 主要 SMTP')
        ->and($action->getModalDescription())
        ->toBe('刪除後將無法復原，既有 Email 活動會保留，但會解除 SMTP 設定檔關聯，確定要進行嗎?');
});

it('describes the permanent impact of deleting an email campaign recipient', function (): void {
    $action = RecipientsRelationManager::deleteAction()
        ->record(new EmailCampaignRecipient(['email' => 'member@example.test']));

    expect($action->getModalHeading())
        ->toBe('刪除 member@example.test')
        ->and($action->getModalDescription())
        ->toBe('刪除後將無法復原，且會一併刪除對應的寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?')
        ->and(RecipientsRelationManager::deleteBulkAction()->getModalDescription())
        ->toBe('刪除後將無法復原，且會一併刪除對應的寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
});

it('shows the selected recipient count in the actual bulk delete action', function (): void {
    $recipients = new Collection([
        new EmailCampaignRecipient(['email' => 'first@example.test']),
        new EmailCampaignRecipient(['email' => 'second@example.test']),
    ]);
    $livewire = Mockery::mock(Component::class)->makePartial();
    $livewire->shouldReceive('getSelectedTableRecords')->andReturn($recipients);
    $relationManager = new RecipientsRelationManager;
    $action = $relationManager
        ->table(Table::make($relationManager))
        ->getBulkAction('delete')
        ->livewire($livewire);

    expect($action->getModalHeading())
        ->toBe('刪除已選取的 2 筆收件人')
        ->and($action->getModalDescription())
        ->toBe('刪除後將無法復原，且會一併刪除對應的寄送與事件紀錄；封鎖紀錄會保留，但會解除來源寄送關聯，確定要進行嗎?');
});
