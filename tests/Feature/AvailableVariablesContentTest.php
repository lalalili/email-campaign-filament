<?php

use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

it('renders the registered variable descriptors for the campaign form', function (): void {
    $method = new ReflectionMethod(EmailCampaignResource::class, 'availableVariablesContent');
    $method->setAccessible(true);

    $html = (string) $method->invoke(null);

    // 預設註冊 System + Recipient provider（見 email-campaign config providers）。
    expect($html)
        ->toContain('{{ campaign_name }}')
        ->toContain('{{ email }}')
        ->toContain('{{ user_name }}')
        ->toContain('活動名稱');
});
