<?php

use Lalalili\EmailCampaignFilament\EmailCampaignFilamentPlugin;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\EmailSmtpProfileResource;

it('can instantiate the plugin', function () {
    $plugin = EmailCampaignFilamentPlugin::make();

    expect($plugin)->toBeInstanceOf(EmailCampaignFilamentPlugin::class)
        ->and($plugin->getId())->toBe('email-campaign');
});

it('can resolve package resources', function () {
    expect(EmailCampaignResource::class)->toBeString()
        ->and(EmailSmtpProfileResource::class)->toBeString();
});
