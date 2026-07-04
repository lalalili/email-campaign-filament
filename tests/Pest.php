<?php

use Lalalili\EmailCampaignFilament\Tests\FilamentTestCase;
use Lalalili\EmailCampaignFilament\Tests\TestCase;

uses(FilamentTestCase::class)->in('Feature/CampaignActionAuthorizationTest.php');
uses(TestCase::class)
    ->in(...array_values(array_diff(
        array_map(
            fn (string $path): string => 'Feature/'.basename($path),
            glob(__DIR__.'/Feature/*Test.php') ?: [],
        ),
        ['Feature/CampaignActionAuthorizationTest.php'],
    )));
