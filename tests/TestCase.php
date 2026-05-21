<?php

namespace Lalalili\EmailCampaignFilament\Tests;

use Lalalili\EmailCampaign\EmailCampaignServiceProvider;
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentServiceProvider;
use Lalalili\PackageTestingSupport\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EmailCampaignServiceProvider::class,
            EmailCampaignFilamentServiceProvider::class,
        ];
    }
}
