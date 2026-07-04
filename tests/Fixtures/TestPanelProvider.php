<?php

namespace Lalalili\EmailCampaignFilament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->plugin(EmailCampaignFilamentPlugin::make());
    }
}
