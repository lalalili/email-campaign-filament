<?php

use Illuminate\Support\Facades\Gate;
use Lalalili\EmailCampaign\Enums\EmailCampaignStatus;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Models\EmailCampaign;
use Lalalili\EmailCampaign\Models\EmailCampaignRecipient;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\Pages\ViewEmailCampaign;
use Lalalili\EmailCampaignFilament\Tests\Fixtures\EmailCampaignTestPolicy;
use Lalalili\EmailCampaignFilament\Tests\Fixtures\User;

beforeEach(function (): void {
    EmailCampaignTestPolicy::reset();
    Gate::policy(EmailCampaign::class, EmailCampaignTestPolicy::class);

    $this->actingAs(User::create([
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]));
});

function seedDelivery(EmailCampaign $campaign, EmailDeliveryStatus $status): void
{
    $recipient = EmailCampaignRecipient::create([
        'email_campaign_id' => $campaign->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    EmailDelivery::create([
        'email_campaign_id' => $campaign->id,
        'email_campaign_recipient_id' => $recipient->id,
        'status' => $status,
        'to_email' => $recipient->email,
    ]);
}

it('computes delivery statistics with the correct success rate', function (): void {
    $campaign = EmailCampaign::create([
        'name' => '統計測試活動',
        'subject_template' => 'Hi',
        'html_template' => '<p>Hi</p>',
        'status' => EmailCampaignStatus::Sending,
    ]);

    // 4 位收件人：2 已寄出、1 失敗、1 略過 → 成功率 50%。
    seedDelivery($campaign, EmailDeliveryStatus::Sent);
    seedDelivery($campaign, EmailDeliveryStatus::Sent);
    seedDelivery($campaign, EmailDeliveryStatus::Failed);
    seedDelivery($campaign, EmailDeliveryStatus::Skipped);

    $page = new ViewEmailCampaign;
    $page->bootedInteractsWithActions();
    $page->mount($campaign->id);

    expect($page->deliveryStats())->toMatchArray([
        'total' => 4,
        'sent' => 2,
        'failed' => 1,
        'skipped' => 1,
        'pending' => 0,
        'rate' => 50.0,
    ]);
});

it('reports a zero success rate when there are no recipients', function (): void {
    $campaign = EmailCampaign::create([
        'name' => '空活動',
        'subject_template' => 'Hi',
        'html_template' => '<p>Hi</p>',
        'status' => EmailCampaignStatus::Draft,
    ]);

    $page = new ViewEmailCampaign;
    $page->bootedInteractsWithActions();
    $page->mount($campaign->id);

    expect($page->deliveryStats())->toMatchArray(['total' => 0, 'sent' => 0, 'rate' => 0.0]);
});
