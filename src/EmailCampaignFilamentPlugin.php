<?php

namespace Lalalili\EmailCampaignFilament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Lalalili\EmailCampaignFilament\Filament\Resources\EmailSmtpProfiles\EmailSmtpProfileResource;

class EmailCampaignFilamentPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'email-campaign';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            EmailCampaignResource::class,
            EmailSmtpProfileResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }
}
