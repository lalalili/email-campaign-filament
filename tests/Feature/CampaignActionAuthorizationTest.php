<?php

use Illuminate\Support\Facades\Gate;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ViewEmailCampaign;
use Lalalili\EmailCampaignFilament\Tests\Fixtures\EmailCampaignTestPolicy;
use Lalalili\EmailCampaignFilament\Tests\Fixtures\User;

function makeAuthTestCampaign(EmailCampaignStatus $status = EmailCampaignStatus::Draft): EmailCampaign
{
    return EmailCampaign::create([
        'name' => '授權測試活動',
        'subject_template' => 'Hi',
        'html_template' => '<p>Hi</p>',
        'status' => $status,
    ]);
}

/**
 * 直接實例化頁面（不經完整 Livewire 渲染）取得 header action，
 * Action::isHidden() 已涵蓋 visible/hidden 與 authorize 判斷。
 */
function mountViewCampaignPage(EmailCampaign $campaign): ViewEmailCampaign
{
    $page = new ViewEmailCampaign;
    $page->bootedInteractsWithActions();
    $page->mount($campaign->id);

    return $page;
}

beforeEach(function (): void {
    EmailCampaignTestPolicy::reset();
    Gate::policy(EmailCampaign::class, EmailCampaignTestPolicy::class);

    $this->actingAs(User::create([
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]));
});

it('hides send, schedule and test_send actions when the user cannot update the campaign', function (): void {
    EmailCampaignTestPolicy::$allowUpdate = false;
    $page = mountViewCampaignPage(makeAuthTestCampaign());

    expect($page->getAction('send')->isHidden())->toBeTrue()
        ->and($page->getAction('schedule')->isHidden())->toBeTrue()
        ->and($page->getAction('test_send')->isHidden())->toBeTrue();
});

it('shows send, schedule and test_send actions when the user can update the campaign', function (): void {
    $page = mountViewCampaignPage(makeAuthTestCampaign());

    expect($page->getAction('send')->isHidden())->toBeFalse()
        ->and($page->getAction('schedule')->isHidden())->toBeFalse()
        ->and($page->getAction('test_send')->isHidden())->toBeFalse();
});

it('hides the reset_stalled action for stalled campaigns when the user cannot update', function (): void {
    EmailCampaignTestPolicy::$allowUpdate = false;
    $page = mountViewCampaignPage(makeAuthTestCampaign(EmailCampaignStatus::Sending));

    expect($page->getAction('reset_stalled')->isHidden())->toBeTrue();
});

it('cancels a scheduled campaign back to draft and clears the schedule', function (): void {
    $campaign = makeAuthTestCampaign(EmailCampaignStatus::Scheduled);
    $campaign->update(['scheduled_at' => now()->addHour()]);
    $page = mountViewCampaignPage($campaign);

    $page->mountAction('cancel_schedule');
    $page->callMountedAction();

    expect($campaign->fresh()->status)->toBe(EmailCampaignStatus::Draft)
        ->and($campaign->fresh()->scheduled_at)->toBeNull();
});
