<?php

namespace Lalalili\EmailCampaignFilament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EmailCampaignFilamentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('email-campaign-filament')
            ->hasConfigFile();
    }
}
