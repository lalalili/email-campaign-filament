<?php

use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;

function renderAvailableVariables(mixed $audienceListId = null): string
{
    $method = new ReflectionMethod(EmailCampaignResource::class, 'availableVariablesContent');
    $method->setAccessible(true);

    return (string) $method->invoke(null, $audienceListId);
}

it('renders the registered variable descriptors for the campaign form', function (): void {
    $html = renderAvailableVariables();

    // 預設註冊 System + Recipient provider（見 email-campaign config providers）。
    expect($html)
        ->toContain('{{ campaign_name }}')
        ->toContain('{{ email }}')
        ->toContain('{{ user_name }}')
        ->toContain('活動名稱');
});

it('prompts to pick an audience list when none is selected', function (): void {
    expect(renderAvailableVariables())->toContain('選擇名單後');
});

it('lists the selected audience list columns as usable variables', function (): void {
    $list = AudienceList::create([
        'name' => '車主名單',
        'columns_json' => [
            ['key' => 'owner_name', 'label' => '車主姓名', 'type' => 'string'],
            ['key' => 'regono', 'label' => '車牌號碼', 'type' => 'string'],
        ],
    ]);

    $html = renderAvailableVariables($list->id);

    expect($html)
        ->toContain('{{ owner_name }}')
        ->toContain('車主姓名')
        ->toContain('{{ regono }}')
        ->toContain('車牌號碼');
});

it('omits audience columns that collide with reserved system variables', function (): void {
    $list = AudienceList::create([
        'name' => '含保留鍵的名單',
        'columns_json' => [
            // RecipientVariableProvider 保留 email，名單同名欄位不會覆蓋它，
            // 列出來會讓作者以為用的是名單值。
            ['key' => 'email', 'label' => '名單 Email', 'type' => 'string'],
            ['key' => 'regono', 'label' => '車牌號碼', 'type' => 'string'],
        ],
    ]);

    $html = renderAvailableVariables($list->id);

    expect($html)
        ->toContain('{{ regono }}')
        ->not->toContain('名單 Email');
});

it('reports an audience list without usable columns', function (): void {
    $list = AudienceList::create(['name' => '空名單']);

    expect(renderAvailableVariables($list->id))->toContain('沒有可用的欄位變數');
});
