<?php

namespace Lalalili\EmailCampaignFilament\Tests;

use Lalalili\EmailCampaign\EmailCampaignServiceProvider;
use Lalalili\EmailCampaignFilament\EmailCampaignFilamentServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EmailCampaignServiceProvider::class,
            EmailCampaignFilamentServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
