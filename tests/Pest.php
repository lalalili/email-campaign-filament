<?php

use Lalalili\EmailCampaignFilament\Tests\FilamentTestCase;
use Lalalili\EmailCampaignFilament\Tests\TestCase;

$filamentTestFiles = [
    'Feature/CampaignActionAuthorizationTest.php',
    'Feature/CampaignStatsTest.php',
];

uses(FilamentTestCase::class)->in(...$filamentTestFiles);
uses(TestCase::class)
    ->in(...array_values(array_diff(
        array_map(
            fn (string $path): string => 'Feature/'.basename($path),
            glob(__DIR__.'/Feature/*Test.php') ?: [],
        ),
        $filamentTestFiles,
    )));
